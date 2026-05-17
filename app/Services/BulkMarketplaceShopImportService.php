<?php

namespace App\Services;

use App\Models\MarketplaceShopImport;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkMarketplaceShopImportService
{
    public function __construct(
        private readonly MarketplaceShopProductFetchService $shopProductFetchService,
        private readonly BulkMarketplaceProductImportService $productImportService,
    ) {
    }

    /**
     * @param array<int,string> $shopUrls
     */
    public function importShopUrlsSequentially(array $shopUrls): void
    {
        $linksToImport = collect();

        foreach ($shopUrls as $shopUrl) {
            try {
                $shopIdentity = $this->shopProductFetchService->resolveShopFromSeedProductUrl($shopUrl);
                $productLinks = $this->shopProductFetchService->fetchProductLinksForShop(
                    $shopIdentity['shop_id'],
                    $shopIdentity['seller_id'],
                );

                $seedProductLink = $this->canonicalProductLink(
                    $shopIdentity['seed_platform'],
                    $shopIdentity['seed_num_iid'],
                );
                $linksToImport = $linksToImport
                    ->push($seedProductLink)
                    ->merge($productLinks);
            } catch (Throwable $exception) {
                Log::warning('Bulk marketplace shop import failed for one shop.', [
                    'shop_url' => $shopUrl,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $uniqueLinks = $linksToImport
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($uniqueLinks === []) {
            return;
        }

        $this->productImportService->importLinksSequentially($uniqueLinks);
    }

    /**
     * @param array<int,int> $shopImportIds
     */
    public function importTrackedShopUrlsSequentially(array $shopImportIds): void
    {
        $imports = MarketplaceShopImport::query()
            ->whereIn('id', $shopImportIds)
            ->get()
            ->keyBy('id');
        $linksToImport = collect();

        foreach ($shopImportIds as $shopImportId) {
            /** @var MarketplaceShopImport|null $shopImport */
            $shopImport = $imports->get($shopImportId);

            if (! $shopImport) {
                continue;
            }

            $shopImport->forceFill([
                'status' => 'resolving_seed',
                'started_at' => $shopImport->started_at ?? now(),
                'error' => null,
                'metadata' => [
                    ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                    'current_stage' => 'resolving_seed',
                    'failed_stage' => null,
                ],
            ])->save();

            try {
                $shopIdentity = $this->shopProductFetchService->resolveShopFromSeedProductUrl($shopImport->seed_url);
                $shopImport->forceFill([
                    'seed_platform' => $shopIdentity['seed_platform'],
                    'seed_num_iid' => $shopIdentity['seed_num_iid'],
                    'seller_id' => $shopIdentity['seller_id'],
                    'shop_id' => $shopIdentity['shop_id'],
                    'raw_seed_payload' => $shopIdentity['raw'],
                    'status' => 'fetching_shop_products',
                    'metadata' => [
                        ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                        'current_stage' => 'fetching_shop_products',
                    ],
                ])->save();

                $productLinks = $this->shopProductFetchService->fetchProductLinksForShop(
                    $shopIdentity['shop_id'],
                    $shopIdentity['seller_id'],
                );
                $seedProductLink = $this->canonicalProductLink(
                    $shopIdentity['seed_platform'],
                    $shopIdentity['seed_num_iid'],
                );
                $shopLinks = collect([$seedProductLink])
                    ->merge($productLinks)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $shopImport->forceFill([
                    'status' => 'products_discovered',
                    'total_product_links' => count($shopLinks),
                    'product_links' => $shopLinks,
                    'metadata' => [
                        ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                        'current_stage' => 'products_discovered',
                    ],
                ])->save();

                $linksToImport = $linksToImport->merge($shopLinks);
            } catch (Throwable $exception) {
                $shopImport->forceFill([
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'metadata' => [
                        ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                        'failed_stage' => $shopImport->status,
                        'exception_class' => $exception::class,
                    ],
                    'completed_at' => now(),
                ])->save();

                Log::warning('Bulk marketplace shop import failed for one tracked shop.', [
                    'shop_import_id' => $shopImport->getKey(),
                    'seed_url' => $shopImport->seed_url,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $uniqueLinks = $linksToImport
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($uniqueLinks === []) {
            return;
        }

        $importsToComplete = MarketplaceShopImport::query()
            ->whereIn('id', $shopImportIds)
            ->where('status', 'products_discovered')
            ->get();

        foreach ($importsToComplete as $shopImport) {
            $shopImport->forceFill([
                'status' => 'importing_products',
                'metadata' => [
                    ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                    'current_stage' => 'importing_products',
                ],
            ])->save();
        }

        try {
            $this->productImportService->importLinksSequentially($uniqueLinks);

            foreach ($importsToComplete as $shopImport) {
                $shopImport->refresh();
                $shopImport->forceFill([
                    'status' => 'completed',
                    'imported_product_links' => (int) $shopImport->total_product_links,
                    'metadata' => [
                        ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                        'current_stage' => 'completed',
                    ],
                    'completed_at' => now(),
                ])->save();
            }
        } catch (Throwable $exception) {
            foreach ($importsToComplete as $shopImport) {
                $shopImport->refresh();
                $shopImport->forceFill([
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'metadata' => [
                        ...(is_array($shopImport->metadata) ? $shopImport->metadata : []),
                        'failed_stage' => 'importing_products',
                        'exception_class' => $exception::class,
                    ],
                    'completed_at' => now(),
                ])->save();
            }

            throw $exception;
        }
    }

    private function canonicalProductLink(string $platform, string $numIid): string
    {
        return match ($platform) {
            '1688' => "https://detail.1688.com/offer/{$numIid}.html",
            'jd' => "https://item.jd.com/{$numIid}.html",
            default => "https://item.taobao.com/item.htm?id={$numIid}",
        };
    }
}
