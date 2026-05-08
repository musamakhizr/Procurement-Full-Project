<?php

namespace App\Services;

use App\Jobs\ClassifyImportedProductCategory;
use App\Jobs\ProcessImportedProductDetailImage;
use App\Jobs\ProcessImportedProductMainImage;
use App\Jobs\ProcessImportedProductMedia;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
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
            'import_total_tasks' => 0,
            'import_completed_tasks' => 0,
        ])->save();

        if (app()->runningUnitTests()) {
            $this->process($product);

            return;
        }

        ProcessImportedProductMedia::dispatch($product->getKey());
    }

    public function dispatchQueuedTasks(Product $product): void
    {
        ['main_image_url' => $mainImageUrl, 'gallery_images' => $galleryImageUrls, 'description_images' => $descriptionImageUrls, 'total_tasks' => $totalTasks] = $this->initializeProcessing($product);

        if ($totalTasks === 0) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        if ($mainImageUrl !== null) {
            ProcessImportedProductMainImage::dispatch($product->getKey(), $mainImageUrl);
        }

        $galleryOffset = $mainImageUrl !== null ? 1 : 0;

        foreach ($galleryImageUrls as $index => $imageUrl) {
            ProcessImportedProductDetailImage::dispatch($product->getKey(), $imageUrl, 'gallery', $index + $galleryOffset);
        }

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            ProcessImportedProductDetailImage::dispatch($product->getKey(), $imageUrl, 'description', $index);
        }

        if ($mainImageUrl !== null) {
            ClassifyImportedProductCategory::dispatch($product->getKey(), $mainImageUrl);
        }
    }

    public function process(Product $product): void
    {
        ['main_image_url' => $mainImageUrl, 'gallery_images' => $galleryImageUrls, 'description_images' => $descriptionImageUrls, 'total_tasks' => $totalTasks] = $this->initializeProcessing($product);

        if ($totalTasks === 0) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        if ($mainImageUrl !== null) {
            $this->processMainImage($product, $mainImageUrl);
        }

        $galleryOffset = $mainImageUrl !== null ? 1 : 0;

        foreach ($galleryImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'gallery', $index + $galleryOffset);
        }

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'description', $index);
        }

        if ($mainImageUrl !== null) {
            $this->classifyCategory($product, $mainImageUrl);
        }
    }

    public function processMainImage(Product $product, string $imageUrl): void
    {
        try {
            $processedImage = $this->fogotProductMediaService->redrawMainImage($imageUrl);

            $stored = $processedImage !== null
                ? $this->productImportImageService->appendProcessedImage($product, [
                    'mime_type' => $processedImage['mime_type'],
                    'data' => $processedImage['data'],
                    'source_url' => $processedImage['source_url'],
                ], 0, 'gallery')
                : $this->productImportImageService->appendRemoteImage($product, $imageUrl, 0, 'gallery');

            $this->markImportTaskFinished($product, $stored ? null : "Unable to store main image [{$imageUrl}].");
        } catch (Throwable $exception) {
            $this->markImportTaskFinished($product, "Main image processing failed for [{$imageUrl}]: {$exception->getMessage()}");
            $this->logTaskWarning('Imported main image processing failed.', $product, $imageUrl, $exception);
        }
    }

    public function processDetailImage(Product $product, string $imageUrl, string $section, int $sortOrder): void
    {
        try {
            $processedImages = $this->fogotProductMediaService->translateImage($imageUrl);
            $processedImage = $processedImages[0] ?? null;

            $stored = $processedImage !== null
                ? $this->productImportImageService->appendProcessedImage($product, [
                    'mime_type' => $processedImage['mime_type'],
                    'data' => $processedImage['data'],
                    'source_url' => $processedImage['source_url'],
                ], $sortOrder, $section)
                : $this->productImportImageService->appendRemoteImage($product, $imageUrl, $sortOrder, $section);

            $this->markImportTaskFinished($product, $stored ? null : "Unable to store {$section} image [{$imageUrl}].");
        } catch (Throwable $exception) {
            $this->markImportTaskFinished($product, ucfirst($section)." image processing failed for [{$imageUrl}]: {$exception->getMessage()}");
            $this->logTaskWarning('Imported detail image processing failed.', $product, $imageUrl, $exception, [
                'section' => $section,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    public function classifyCategory(Product $product, string $imageUrl): void
    {
        try {
            $category = $this->fogotProductMediaService->classifyImage($imageUrl);

            if ($category !== null) {
                $product->forceFill([
                    'cat_from_api' => $category,
                ])->save();
            }

            $this->markImportTaskFinished($product, $category !== null ? null : "Category classification returned empty for [{$imageUrl}].");
        } catch (Throwable $exception) {
            $this->markImportTaskFinished($product, "Category classification failed for [{$imageUrl}]: {$exception->getMessage()}");
            $this->logTaskWarning('Imported category classification failed.', $product, $imageUrl, $exception);
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
     * @return array{
     *   main_image_url:?string,
     *   gallery_images:array<int, string>,
     *   description_images:array<int, string>,
     *   total_tasks:int
     * }
     */
    private function initializeProcessing(Product $product): array
    {
        $importSource = $product->source_payload;

        if (! is_array($importSource) || $importSource === []) {
            return [
                'main_image_url' => null,
                'gallery_images' => [],
                'description_images' => [],
                'total_tasks' => 0,
            ];
        }

        $mainImageUrl = $this->normalizeUrlValue(data_get($importSource, 'main_image_url'))
            ?? $this->normalizeUrlValue(data_get($importSource, 'image_url'));
        $galleryImageUrls = $this->normalizeUrlArray(data_get($importSource, 'images', []));
        $descriptionImageUrls = $this->normalizeUrlArray(data_get($importSource, 'description_images', []));

        if ($mainImageUrl !== null) {
            $galleryImageUrls = array_values(array_filter(
                $galleryImageUrls,
                fn (string $url) => $url !== $mainImageUrl,
            ));
        }

        $totalTasks = count($galleryImageUrls) + count($descriptionImageUrls) + ($mainImageUrl !== null ? 2 : 0);

        $this->productImportImageService->resetProductImages($product);

        $product->forceFill([
            'import_status' => 'processing',
            'import_error' => null,
            'import_total_tasks' => $totalTasks,
            'import_completed_tasks' => 0,
            'cat_from_api' => null,
        ])->save();

        return [
            'main_image_url' => $mainImageUrl,
            'gallery_images' => $galleryImageUrls,
            'description_images' => $descriptionImageUrls,
            'total_tasks' => $totalTasks,
        ];
    }

    private function markImportTaskFinished(Product $product, ?string $error = null): void
    {
        DB::transaction(function () use ($product, $error) {
            /** @var Product $freshProduct */
            $freshProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $completedTasks = (int) ($freshProduct->import_completed_tasks ?? 0) + 1;
            $existingError = $freshProduct->import_error;
            $combinedError = $existingError;

            if ($error !== null && trim($error) !== '') {
                $combinedError = trim(implode("\n", array_filter([
                    is_string($existingError) && trim($existingError) !== '' ? trim($existingError) : null,
                    trim($error),
                ])));
            }

            $freshProduct->forceFill([
                'import_completed_tasks' => $completedTasks,
                'import_error' => $combinedError !== '' ? $combinedError : null,
            ])->save();

            if ($completedTasks >= (int) ($freshProduct->import_total_tasks ?? 0)) {
                $this->finalizeImport($freshProduct);
            }
        });
    }

    private function finalizeImport(Product $product): void
    {
        $product->load('productImages', 'variants');
        $this->syncVariantStoredImages($product);

        $status = $product->productImages->isEmpty() && filled($product->import_error)
            ? 'failed'
            : 'completed';

        $product->forceFill([
            'import_status' => $status,
        ])->save();
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logTaskWarning(string $message, Product $product, string $imageUrl, Throwable $exception, array $extra = []): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Log::warning($message, [
            'product_id' => $product->getKey(),
            'image_url' => $imageUrl,
            'error' => $exception->getMessage(),
            ...$extra,
        ]);
    }
}
