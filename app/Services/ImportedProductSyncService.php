<?php

namespace App\Services;

use App\Jobs\ProcessImportedProductMedia;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportedProductSyncService
{
    public function __construct(
        private readonly FogotProductMediaService $fogotProductMediaService,
        private readonly ProductImportImageService $productImportImageService,
    ) {
    }

    public function schedule(Product $product, ?array $importSource): void
    {
        if (! is_array($importSource) || $importSource === []) {
            return;
        }

        $product->forceFill([
            'import_status' => 'pending',
            'import_error' => null,
            'source_payload' => $this->sanitizeImportSource($importSource),
        ])->save();

        if (app()->runningUnitTests()) {
            $this->process($product);

            return;
        }

        ProcessImportedProductMedia::dispatch($product->getKey());
    }

    public function process(Product $product): void
    {
        $importSource = $product->source_payload;

        if (! is_array($importSource) || $importSource === []) {
            return;
        }

        $product->forceFill([
            'import_status' => 'processing',
            'import_error' => null,
        ])->save();

        try {
            $mainImageUrl = $this->normalizeUrlValue(data_get($importSource, 'main_image_url'))
                ?? $this->normalizeUrlValue(data_get($importSource, 'image_url'));
            $galleryImageUrls = $this->normalizeUrlArray(data_get($importSource, 'images', []));
            $descriptionImageUrls = $this->normalizeUrlArray(data_get($importSource, 'description_images', []));

            $processedMedia = $this->fogotProductMediaService->processImportPreview($mainImageUrl, $galleryImageUrls, $descriptionImageUrls);

            $galleryImages = $this->buildMixedImages(
                $mainImageUrl,
                $galleryImageUrls,
                $processedMedia['main_image'],
                $processedMedia['gallery_images'],
            );
            $descriptionImages = $this->buildMixedImages(
                null,
                $descriptionImageUrls,
                null,
                $processedMedia['description_images'],
            );

            if ($galleryImages !== [] || $descriptionImages !== []) {
                $this->productImportImageService->syncMixedProductImages($product, $galleryImages, $descriptionImages);
                $product->load('productImages', 'variants');
                $this->syncVariantStoredImages($product);
            }

            $product->forceFill([
                'source_image_url' => $mainImageUrl ?? $product->source_image_url,
                'cat_from_api' => $processedMedia['category'] ?? $product->cat_from_api,
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Imported product media processing failed.', [
                'product_id' => $product->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $product->forceFill([
                'import_status' => 'failed',
                'import_error' => $exception->getMessage(),
            ])->save();
        }
    }

    public function syncVariants(Product $product, ?array $importSource): void
    {
        $variants = data_get($importSource, 'variants', []);

        $product->variants()->delete();

        if (! is_array($variants) || $variants === []) {
            return;
        }

        $rows = collect($variants)
            ->map(fn ($variant, int $index) => $this->normalizeVariantRow($variant, $index))
            ->filter()
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        $rows[0]['is_default'] = true;

        $product->variants()->createMany($rows);
    }

    /**
     * @param  array<string, mixed>  $importSource
     * @return array<string, mixed>
     */
    private function sanitizeImportSource(array $importSource): array
    {
        return [
            'platform' => data_get($importSource, 'platform'),
            'num_iid' => data_get($importSource, 'num_iid'),
            'detail_url' => $this->normalizeUrlValue(data_get($importSource, 'detail_url')),
            'image_url' => $this->normalizeUrlValue(data_get($importSource, 'image_url')),
            'main_image_url' => $this->normalizeUrlValue(data_get($importSource, 'main_image_url')),
            'classified_category' => data_get($importSource, 'classified_category'),
            'description' => data_get($importSource, 'description'),
            'description_html' => data_get($importSource, 'description_html'),
            'images' => $this->normalizeUrlArray(data_get($importSource, 'images', [])),
            'description_images' => $this->normalizeUrlArray(data_get($importSource, 'description_images', [])),
            'variants' => collect(data_get($importSource, 'variants', []))
                ->filter(fn ($variant) => is_array($variant))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>  $rawUrls
     * @param  array{mime_type:string,data:string,preview_url:string,source_url:string}|null  $processedMainImage
     * @param  array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>  $processedImages
     * @return array<int, array{type:string,mime_type?:string,data?:string,source_url?:string,url?:string}>
     */
    private function buildMixedImages(?string $mainImageUrl, array $rawUrls, ?array $processedMainImage, array $processedImages): array
    {
        $mixedImages = [];
        $processedBySource = [];

        if ($processedMainImage !== null) {
            $mixedImages[] = [
                'type' => 'processed',
                'mime_type' => $processedMainImage['mime_type'],
                'data' => $processedMainImage['data'],
                'source_url' => $processedMainImage['source_url'],
            ];
            $processedBySource[$processedMainImage['source_url']] = true;
        } elseif ($mainImageUrl !== null) {
            $mixedImages[] = [
                'type' => 'remote',
                'url' => $mainImageUrl,
            ];
        }

        foreach ($processedImages as $processedImage) {
            $sourceUrl = $processedImage['source_url'];

            $mixedImages[] = [
                'type' => 'processed',
                'mime_type' => $processedImage['mime_type'],
                'data' => $processedImage['data'],
                'source_url' => $sourceUrl,
            ];
            $processedBySource[$sourceUrl] = true;
        }

        foreach ($rawUrls as $url) {
            if (! isset($processedBySource[$url])) {
                $mixedImages[] = [
                    'type' => 'remote',
                    'url' => $url,
                ];
            }
        }

        return array_values($mixedImages);
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>|null
     */
    private function normalizeVariantRow(array $variant, int $index): ?array
    {
        $optionValues = collect($variant['option_values'] ?? [])
            ->filter(fn ($option) => is_array($option) && filled($option['group_name'] ?? null) && filled($option['value'] ?? null))
            ->map(fn (array $option) => [
                'key' => (string) ($option['key'] ?? ''),
                'group_name' => (string) $option['group_name'],
                'value' => (string) $option['value'],
            ])
            ->values()
            ->all();

        if ($optionValues === []) {
            return null;
        }

        $price = $this->normalizeDecimal($variant['price'] ?? null);
        $originalPrice = $this->normalizeDecimal($variant['original_price'] ?? null);

        return [
            'source_sku_id' => filled($variant['sku_id'] ?? null) ? (string) $variant['sku_id'] : null,
            'source_properties_key' => filled($variant['properties_key'] ?? null) ? (string) $variant['properties_key'] : null,
            'source_properties_name' => filled($variant['properties_name'] ?? null) ? (string) $variant['properties_name'] : null,
            'label' => filled($variant['label'] ?? null) ? (string) $variant['label'] : implode(' / ', array_column($optionValues, 'value')),
            'option_values' => $optionValues,
            'image_url' => $this->normalizeUrlValue($variant['image_url'] ?? null),
            'source_image_url' => $this->normalizeUrlValue($variant['image_url'] ?? null),
            'stock_quantity' => max((int) ($variant['stock_quantity'] ?? 0), 0),
            'price' => $price ?? 0,
            'original_price' => $originalPrice,
            'is_default' => false,
            'sort_order' => $index,
        ];
    }

    private function syncVariantStoredImages(Product $product): void
    {
        $pathBySourceUrl = $product->productImages
            ->filter(fn ($image) => filled($image->source_url))
            ->mapWithKeys(fn ($image) => [$image->source_url => $image->path]);

        /** @var ProductVariant $variant */
        foreach ($product->variants as $variant) {
            $sourceUrl = $variant->source_image_url;

            if ($sourceUrl !== null && $pathBySourceUrl->has($sourceUrl)) {
                $variant->forceFill([
                    'image_url' => $pathBySourceUrl->get($sourceUrl),
                ])->save();
            }
        }
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeUrlArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($url) => $this->normalizeUrlValue($url))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeUrlValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
