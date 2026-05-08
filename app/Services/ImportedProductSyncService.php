<?php

namespace App\Services;

use App\Jobs\ClassifyImportedProductCategory;
use App\Jobs\ProcessImportedProductDetailImage;
use App\Jobs\ProcessImportedProductMainImage;
use App\Jobs\ProcessImportedProductMedia;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
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
        ['main_image_url' => $mainImageUrl, 'gallery_images' => $galleryImageUrls, 'variant_images' => $variantImageUrls, 'description_images' => $descriptionImageUrls, 'total_tasks' => $totalTasks] = $this->initializeProcessing($product);

        if ($totalTasks === 0) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        $jobs = [];
        $galleryOffset = $mainImageUrl !== null ? 1 : 0;

        if ($this->shouldClassifyProductCategory($product)) {
            $jobs[] = new ClassifyImportedProductCategory($product->getKey());
        }

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'description', $index, 'translate');
        }

        $variantOffset = $galleryOffset + count($galleryImageUrls);

        foreach ($variantImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'gallery', $index + $variantOffset, 'translate');
        }

        foreach ($galleryImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'gallery', $index + $galleryOffset, 'redraw');
        }

        if ($mainImageUrl !== null) {
            $jobs[] = new ProcessImportedProductMainImage($product->getKey(), $mainImageUrl);
        }

        if ($jobs === []) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        Bus::chain($jobs)->dispatch();
    }

    public function process(Product $product): void
    {
        ['main_image_url' => $mainImageUrl, 'gallery_images' => $galleryImageUrls, 'variant_images' => $variantImageUrls, 'description_images' => $descriptionImageUrls, 'total_tasks' => $totalTasks] = $this->initializeProcessing($product);

        if ($totalTasks === 0) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        if ($this->shouldClassifyProductCategory($product)) {
            $this->classifyCategory($product);
        }

        $galleryOffset = $mainImageUrl !== null ? 1 : 0;

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'description', $index, 'translate');
        }

        $variantOffset = $galleryOffset + count($galleryImageUrls);

        foreach ($variantImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'gallery', $index + $variantOffset, 'translate');
        }

        foreach ($galleryImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'gallery', $index + $galleryOffset, 'redraw');
        }

        if ($mainImageUrl !== null) {
            $this->processMainImage($product, $mainImageUrl);
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

    public function processDetailImage(Product $product, string $imageUrl, string $section, int $sortOrder, string $processor = 'translate'): void
    {
        try {
            if ($section === 'description' && ! $this->fogotProductMediaService->shouldKeepDescriptionImage($imageUrl)) {
                $this->markImportTaskFinished($product);

                return;
            }

            $processedImage = match ($processor) {
                'redraw' => $this->fogotProductMediaService->redrawImage($imageUrl),
                default => $this->fogotProductMediaService->translateImage($imageUrl)[0] ?? null,
            };

            $stored = $processedImage !== null
                ? $this->productImportImageService->appendProcessedImage($product, [
                    'mime_type' => $processedImage['mime_type'],
                    'data' => $processedImage['data'],
                    'source_url' => $processedImage['source_url'],
                ], $sortOrder, $section)
                : $this->productImportImageService->appendRemoteImage($product, $imageUrl, $sortOrder, $section);

            if ($section === 'description') {
                $this->rememberApprovedDescriptionImage($product, $imageUrl);
            }

            $this->markImportTaskFinished($product, $stored ? null : "Unable to store {$section} image [{$imageUrl}].");
        } catch (Throwable $exception) {
            $this->markImportTaskFinished($product, ucfirst($section)." image processing failed for [{$imageUrl}]: {$exception->getMessage()}");
            $this->logTaskWarning('Imported detail image processing failed.', $product, $imageUrl, $exception, [
                'section' => $section,
                'sort_order' => $sortOrder,
                'processor' => $processor,
            ]);
        }
    }

    public function classifyCategory(Product $product): void
    {
        try {
            $productText = $this->buildProductCategoryText($product);
            $category = $this->fogotProductMediaService->classifyProductCategory($productText, [
                'item_name' => (string) (data_get($product->source_payload, 'title') ?? $product->name),
                'description' => (string) (data_get($product->source_payload, 'description') ?? $product->description),
                'picture' => $product->source_image_url
                    ?? data_get($product->source_payload, 'main_image_url')
                    ?? data_get($product->source_payload, 'image_url')
                    ?? '',
                'link' => $product->source_url ?? data_get($product->source_payload, 'detail_url') ?? '',
                'delivery_date' => '',
                'note' => '',
            ]);

            if ($category !== null) {
                $product->forceFill([
                    'cat_from_api' => $category,
                ])->save();
            }

            if ($category === null) {
                $this->logTaskWarning('Imported product category classification returned an empty result.', $product, $product->source_image_url ?? '');
            }

            $this->markImportTaskFinished($product);
        } catch (Throwable $exception) {
            $this->markImportTaskFinished($product, "Category classification failed: {$exception->getMessage()}");
            $this->logTaskWarning('Imported category classification failed.', $product, $product->source_image_url ?? '', $exception);
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
            'title' => data_get($importSource, 'title'),
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
     *   variant_images:array<int, string>,
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
                'variant_images' => [],
                'description_images' => [],
                'total_tasks' => 0,
            ];
        }

        $mainImageUrl = $this->normalizeUrlValue(data_get($importSource, 'main_image_url'))
            ?? $this->normalizeUrlValue(data_get($importSource, 'image_url'));
        $galleryImageUrls = $this->normalizeUrlArray(data_get($importSource, 'images', []));
        $variantImageUrls = $this->variantImageUrlsFromImportSource($importSource);
        $descriptionImageUrls = $this->normalizeUrlArray(data_get($importSource, 'description_images', []));

        if ($mainImageUrl !== null) {
            $galleryImageUrls = array_values(array_filter(
                $galleryImageUrls,
                fn (string $url) => $url !== $mainImageUrl,
            ));
        }

        $variantImageUrls = array_values(array_filter(
            $variantImageUrls,
            fn (string $url) => $url !== $mainImageUrl && ! in_array($url, $galleryImageUrls, true),
        ));

        $totalTasks = count($galleryImageUrls)
            + count($variantImageUrls)
            + count($descriptionImageUrls)
            + ($mainImageUrl !== null ? 1 : 0)
            + ($this->shouldClassifyProductCategory($product) ? 1 : 0);

        $this->productImportImageService->resetProductImages($product);

        $product->forceFill([
            'import_status' => 'processing',
            'import_error' => null,
            'import_total_tasks' => $totalTasks,
            'import_completed_tasks' => 0,
            'cat_from_api' => null,
            'source_payload' => $this->withApprovedDescriptionImagesReset($importSource),
        ])->save();

        return [
            'main_image_url' => $mainImageUrl,
            'gallery_images' => $galleryImageUrls,
            'variant_images' => $variantImageUrls,
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
        $this->finalizeApprovedDescriptionImages($product);
        $product->load('productImages', 'variants');
        $this->syncVariantStoredImages($product);

        $status = $product->productImages->isEmpty() && filled($product->import_error)
            ? 'failed'
            : 'completed';

        $product->forceFill([
            'import_status' => $status,
        ])->save();
    }

    private function shouldClassifyProductCategory(Product $product): bool
    {
        return trim($this->buildProductCategoryText($product)) !== '';
    }

    private function buildProductCategoryText(Product $product): string
    {
        return trim(implode("\n", array_filter([
            $product->name,
            $product->description,
        ])));
    }

    /**
     * @param  array<string, mixed>  $importSource
     * @return array<string, mixed>
     */
    private function withApprovedDescriptionImagesReset(array $importSource): array
    {
        $importSource['approved_description_images'] = [];

        return $importSource;
    }

    private function rememberApprovedDescriptionImage(Product $product, string $imageUrl): void
    {
        DB::transaction(function () use ($product, $imageUrl) {
            /** @var Product $freshProduct */
            $freshProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $payload = is_array($freshProduct->source_payload) ? $freshProduct->source_payload : [];
            $approvedImages = collect($payload['approved_description_images'] ?? [])
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->push($imageUrl)
                ->unique()
                ->values()
                ->all();

            $payload['approved_description_images'] = $approvedImages;

            $freshProduct->forceFill([
                'source_payload' => $payload,
            ])->save();
        });
    }

    private function finalizeApprovedDescriptionImages(Product $product): void
    {
        $payload = is_array($product->source_payload) ? $product->source_payload : [];

        if (! array_key_exists('approved_description_images', $payload)) {
            return;
        }

        $payload['description_images'] = collect($payload['approved_description_images'] ?? [])
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        unset($payload['approved_description_images']);

        $product->forceFill([
            'source_payload' => $payload,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $importSource
     * @return array<int, string>
     */
    private function variantImageUrlsFromImportSource(array $importSource): array
    {
        return collect(data_get($importSource, 'variants', []))
            ->map(fn ($variant) => is_array($variant) ? $this->normalizeUrlValue($variant['image_url'] ?? null) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
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
