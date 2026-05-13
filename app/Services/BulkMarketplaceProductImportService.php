<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BulkMarketplaceProductImportService
{
    public function __construct(
        private readonly MarketplaceProductFetchService $marketplaceProductFetchService,
        private readonly ImportedProductSyncService $importedProductSyncService,
    ) {
    }

    /**
     * Fetch, insert/update, and fully process each product before starting the next link.
     *
     * @param array<int,string> $links
     */
    public function importLinksSequentially(array $links): void
    {
        $productsToProcess = [];

        foreach ($links as $link) {
            try {
                $result = $this->marketplaceProductFetchService->fetch($link);
                $source = $result['product'];
                $product = $this->upsertProduct($source);

                $this->importedProductSyncService->preparePending($product, $source);
                $productsToProcess[] = [
                    'product_id' => $product->getKey(),
                    'source' => $source,
                ];
            } catch (Throwable $exception) {
                Log::warning('Bulk marketplace product import failed for one link.', [
                    'link' => $link,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($productsToProcess as $productToProcess) {
            $product = Product::query()->find($productToProcess['product_id']);

            if (! $product) {
                continue;
            }

            $this->importedProductSyncService->processSequentially($product, $productToProcess['source']);
        }
    }

    /**
     * @param array<string,mixed> $source
     */
    private function upsertProduct(array $source): Product
    {
        return DB::transaction(function () use ($source) {
            $platform = (string) data_get($source, 'platform', 'marketplace');
            $sourceProductId = (string) data_get($source, 'num_iid', '');
            $sku = $this->buildSku($platform, $sourceProductId);

            $product = Product::query()
                ->where('source_platform', $platform)
                ->where('source_product_id', $sourceProductId)
                ->first();

            if (! $product) {
                $product = Product::query()->where('sku', $sku)->first() ?? new Product();
            }

            $basePrice = $this->basePrice($source);

            $product->forceFill([
                'category_id' => $product->category_id ?? $this->fallbackCategoryId(),
                'sku' => $product->exists ? $product->sku : $sku,
                'name' => Str::limit((string) data_get($source, 'title', 'Imported product'), 250, ''),
                'description' => (string) data_get($source, 'description', ''),
                'image_url' => data_get($source, 'main_image_url') ?? data_get($source, 'image_url'),
                'source_platform' => $platform,
                'source_product_id' => $sourceProductId,
                'source_url' => data_get($source, 'detail_url'),
                'source_image_url' => data_get($source, 'main_image_url') ?? data_get($source, 'image_url'),
                'source_category_label' => data_get($source, 'classified_category'),
                'cat_from_api' => null,
                'import_status' => 'pending',
                'import_error' => null,
                'moq' => max((int) data_get($source, 'moq', 1), 1),
                'lead_time_min_days' => 3,
                'lead_time_max_days' => 5,
                'stock_quantity' => $this->stockQuantity($source),
                'is_verified' => true,
                'is_customizable' => false,
                'is_active' => true,
                'base_price' => $basePrice,
            ])->save();

            $this->syncBasePriceTier($product, $basePrice);
            $this->importedProductSyncService->syncVariants($product, $source);

            return $product->refresh();
        });
    }

    private function fallbackCategoryId(): int
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'imported-products'],
            [
                'name' => 'Imported Products',
                'parent_id' => null,
                'sort_order' => 999,
            ],
        );

        return (int) $category->getKey();
    }

    private function buildSku(string $platform, string $sourceProductId): string
    {
        $prefix = Str::upper(Str::limit($platform, 12, ''));
        $identifier = $sourceProductId !== '' ? $sourceProductId : Str::random(10);

        return Str::limit("{$prefix}-{$identifier}", 100, '');
    }

    /**
     * @param array<string,mixed> $source
     */
    private function basePrice(array $source): float
    {
        $price = data_get($source, 'original_price') ?? data_get($source, 'price') ?? 0;

        if (is_string($price)) {
            $price = preg_replace('/[^\d.]/', '', $price) ?: 0;
        }

        return round(max((float) $price, 0), 2);
    }

    /**
     * @param array<string,mixed> $source
     */
    private function stockQuantity(array $source): int
    {
        $sourceStock = (int) data_get($source, 'stock_quantity', 0);

        if ($sourceStock > 0) {
            return $sourceStock;
        }

        return collect(data_get($source, 'variants', []))
            ->filter(fn ($variant) => is_array($variant))
            ->sum(fn (array $variant) => max((int) ($variant['stock_quantity'] ?? 0), 0));
    }

    private function syncBasePriceTier(Product $product, float $basePrice): void
    {
        $product->priceTiers()->delete();
        $product->priceTiers()->create([
            'min_quantity' => 1,
            'max_quantity' => null,
            'price' => $basePrice,
        ]);
    }
}
