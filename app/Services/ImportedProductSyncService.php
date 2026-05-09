<?php

namespace App\Services;

use App\Jobs\ClassifyImportedProductCategory;
use App\Jobs\ProcessImportedProductDetailImage;
use App\Jobs\ProcessImportedProductMainImage;
use App\Jobs\ProcessImportedProductMedia;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'import_api_debug' => null,
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
        if ($this->shouldClassifyProductCategory($product)) {
            $jobs[] = new ClassifyImportedProductCategory($product->getKey());
        }

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'description', $index, 'translate');
        }

        foreach ($variantImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'variant', $index, 'translate');
        }

        if ($mainImageUrl !== null) {
            $jobs[] = new ProcessImportedProductMainImage($product->getKey(), $mainImageUrl);
        }

        foreach ($galleryImageUrls as $index => $imageUrl) {
            $jobs[] = new ProcessImportedProductDetailImage($product->getKey(), $imageUrl, 'gallery', $index + 1, 'redraw');
        }

        if ($jobs === []) {
            $product->forceFill([
                'import_status' => 'completed',
                'import_error' => null,
            ])->save();

            return;
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
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

        foreach ($descriptionImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'description', $index, 'translate');
        }

        foreach ($variantImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'variant', $index, 'translate');
        }

        if ($mainImageUrl !== null) {
            $this->processMainImage($product, $mainImageUrl);
        }

        foreach ($galleryImageUrls as $index => $imageUrl) {
            $this->processDetailImage($product, $imageUrl, 'gallery', $index + 1, 'redraw');
        }
    }

    public function processMainImage(Product $product, string $imageUrl): void
    {
        try {
            $processedImage = $this->fogotProductMediaService->redrawMainImage($imageUrl);
            $this->rememberImageProcessingResult($product, 'redraw_gallery_results', $imageUrl, $processedImage !== null ? 'processed' : 'empty');

            if ($processedImage === null) {
                $this->markImportTaskFinished($product, "Redraw API returned no processed main image for [{$imageUrl}].");

                return;
            }

            $stored = $this->productImportImageService->appendProcessedImage($product, [
                'mime_type' => $processedImage['mime_type'],
                'data' => $processedImage['data'],
                'source_url' => $processedImage['source_url'],
            ], 0, 'gallery');

            $this->markImportTaskFinished($product, $stored ? null : "Unable to store main image [{$imageUrl}].");
        } catch (Throwable $exception) {
            $this->rememberImageProcessingResult($product, 'redraw_gallery_results', $imageUrl, 'failed');
            $this->markImportTaskFinished($product, "Main image processing failed for [{$imageUrl}]: {$exception->getMessage()}");
            $this->logTaskWarning('Imported main image processing failed.', $product, $imageUrl, $exception);
        }
    }

    public function processDetailImage(Product $product, string $imageUrl, string $section, int $sortOrder, string $processor = 'translate'): void
    {
        try {
            if ($section === 'description' && ! $this->fogotProductMediaService->shouldKeepDescriptionImage($imageUrl)) {
                $this->rememberImageProcessingResult($product, 'classify_description_results', $imageUrl, 'rejected');
                $this->markImportTaskFinished($product);

                return;
            }

            $processedImage = match ($processor) {
                'redraw' => $this->fogotProductMediaService->redrawImage($imageUrl),
                default => $this->fogotProductMediaService->translateImage($imageUrl)[0] ?? null,
            };

            if ($section === 'description') {
                $this->rememberImageProcessingResult($product, 'classify_description_results', $imageUrl, 'approved');
            }

            $resultKey = match ($processor) {
                'redraw' => 'redraw_gallery_results',
                default => $section === 'description' ? 'translate_description_results' : 'translate_variant_results',
            };

            $this->rememberImageProcessingResult($product, $resultKey, $imageUrl, $processedImage !== null ? 'processed' : 'empty');

            if ($processedImage === null) {
                $this->markImportTaskFinished($product, "Processor [{$processor}] returned no processed {$section} image for [{$imageUrl}].");

                return;
            }

            $stored = $this->productImportImageService->appendProcessedImage($product, [
                'mime_type' => $processedImage['mime_type'],
                'data' => $processedImage['data'],
                'source_url' => $processedImage['source_url'],
            ], $sortOrder, $section);

            if ($section === 'description') {
                $this->rememberApprovedDescriptionImage($product, $imageUrl);
            }

            $this->markImportTaskFinished($product, $stored ? null : "Unable to store {$section} image [{$imageUrl}].");
        } catch (Throwable $exception) {
            $resultKey = match ($processor) {
                'redraw' => 'redraw_gallery_results',
                default => $section === 'description' ? 'translate_description_results' : 'translate_variant_results',
            };
            $this->rememberImageProcessingResult($product, $resultKey, $imageUrl, 'failed');
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
            $categoryRequestItem = [
                'item_name' => (string) (data_get($product->source_payload, 'title') ?? $product->name),
                'description' => (string) (data_get($product->source_payload, 'description') ?? $product->description),
                'picture' => $product->source_image_url
                    ?? data_get($product->source_payload, 'main_image_url')
                    ?? data_get($product->source_payload, 'image_url')
                    ?? '',
                'link' => $product->source_url ?? data_get($product->source_payload, 'detail_url') ?? '',
                'delivery_date' => '',
                'note' => '',
            ];
            $category = $this->fogotProductMediaService->classifyProductCategory($productText, $categoryRequestItem);

            if ($category !== null) {
                $payload = is_array($product->source_payload) ? $product->source_payload : [];
                $payload['classified_category'] = $category;

                $product->forceFill([
                    'cat_from_api' => $category,
                    'source_payload' => $payload,
                ])->save();
            }

            $this->rememberCategoryDebug($product, $productText, $categoryRequestItem, $category);

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
        $galleryImageUrls = array_slice($this->normalizeUrlArray(data_get($importSource, 'images', [])), 0, 4);
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
            fn (string $url) => $url !== $mainImageUrl,
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
            'import_api_debug' => $this->buildImportApiDebug($product, $mainImageUrl, $galleryImageUrls, $variantImageUrls, $descriptionImageUrls),
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
            $debug = is_array($freshProduct->import_api_debug) ? $freshProduct->import_api_debug : [];
            $debug['approved_description_urls'] = collect($debug['approved_description_urls'] ?? [])
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->push($imageUrl)
                ->unique()
                ->values()
                ->all();
            $debug['translate_description_urls'] = collect($debug['translate_description_urls'] ?? [])
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->push($imageUrl)
                ->unique()
                ->values()
                ->all();

            $freshProduct->forceFill([
                'source_payload' => $payload,
                'import_api_debug' => $debug,
            ])->save();
        });
    }

    private function finalizeApprovedDescriptionImages(Product $product): void
    {
        $payload = is_array($product->source_payload) ? $product->source_payload : [];

        if (! array_key_exists('approved_description_images', $payload)) {
            return;
        }

        $approvedDescriptionImages = collect($payload['approved_description_images'] ?? [])
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $payload['description_images'] = $approvedDescriptionImages;
        $payload['approved_description_images'] = $approvedDescriptionImages;

        $product->forceFill([
            'source_payload' => $payload,
        ])->save();
    }

    private function rememberCategoryDebug(Product $product, string $productText, array $item, ?array $category): void
    {
        $debug = is_array($product->import_api_debug) ? $product->import_api_debug : [];
        $debug['category_request'] = [
            'product_text' => $productText,
            'item' => $item,
        ];
        $debug['category_response'] = $category;

        $product->forceFill([
            'import_api_debug' => $debug,
        ])->save();
    }

    /**
     * @param  array<int, string>  $galleryImageUrls
     * @param  array<int, string>  $variantImageUrls
     * @param  array<int, string>  $descriptionImageUrls
     * @return array<string, mixed>
     */
    private function buildImportApiDebug(Product $product, ?string $mainImageUrl, array $galleryImageUrls, array $variantImageUrls, array $descriptionImageUrls): array
    {
        $categoryItem = [
            'item_name' => (string) (data_get($product->source_payload, 'title') ?? $product->name),
            'description' => (string) (data_get($product->source_payload, 'description') ?? $product->description),
            'picture' => $product->source_image_url
                ?? data_get($product->source_payload, 'main_image_url')
                ?? data_get($product->source_payload, 'image_url')
                ?? '',
            'link' => $product->source_url ?? data_get($product->source_payload, 'detail_url') ?? '',
            'delivery_date' => '',
            'note' => '',
        ];

        return [
            'redraw_gallery_urls' => array_values(array_filter([
                $mainImageUrl,
                ...$galleryImageUrls,
            ])),
            'redraw_gallery_results' => [],
            'classify_description_urls' => array_values($descriptionImageUrls),
            'classify_description_results' => [],
            'approved_description_urls' => [],
            'translate_description_urls' => [],
            'translate_description_results' => [],
            'translate_variant_urls' => array_values($variantImageUrls),
            'translate_variant_results' => [],
            'category_request' => [
                'product_text' => $this->buildProductCategoryText($product),
                'item' => $categoryItem,
            ],
            'category_response' => null,
        ];
    }

    private function rememberImageProcessingResult(Product $product, string $key, string $imageUrl, string $status): void
    {
        DB::transaction(function () use ($product, $key, $imageUrl, $status) {
            /** @var Product $freshProduct */
            $freshProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $debug = is_array($freshProduct->import_api_debug) ? $freshProduct->import_api_debug : [];
            $results = collect($debug[$key] ?? [])
                ->filter(fn ($row) => is_array($row) && is_string($row['url'] ?? null))
                ->reject(fn (array $row) => $row['url'] === $imageUrl)
                ->push([
                    'url' => $imageUrl,
                    'status' => $status,
                ])
                ->values()
                ->all();

            $debug[$key] = $results;

            $freshProduct->forceFill([
                'import_api_debug' => $debug,
            ])->save();
        });
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
        $preferredVariantPaths = $product->productImages
            ->where('section', 'variant')
            ->filter(fn ($image) => filled($image->source_url))
            ->mapWithKeys(fn ($image) => [$image->source_url => $image->path]);

        $fallbackPaths = $product->productImages
            ->filter(fn ($image) => filled($image->source_url))
            ->mapWithKeys(fn ($image) => [$image->source_url => $image->path]);

        $pathBySourceUrl = $fallbackPaths->merge($preferredVariantPaths);

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
            ->filter(fn ($url) => is_string($url) && ! $this->shouldIgnoreImageUrl($url))
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

    private function shouldIgnoreImageUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if ($host === 'www.o0b.cn' && $path === '/i.php') {
            return true;
        }

        return Str::endsWith($path, '/spaceball.gif');
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
    private function logTaskWarning(string $message, Product $product, string $imageUrl, ?Throwable $exception = null, array $extra = []): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $context = [
            'product_id' => $product->getKey(),
            'image_url' => $imageUrl,
            ...$extra,
        ];

        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
        }

        Log::warning($message, $context);
    }
}
