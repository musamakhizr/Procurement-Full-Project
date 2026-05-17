<?php

namespace App\Jobs;

use App\Models\MarketplaceShopImport;
use App\Services\BulkMarketplaceShopImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportMarketplaceProductsFromShops implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 21600;

    public int $tries = 3;

    public bool $failOnTimeout = false;

    /**
     * @param array<int,int> $shopImportIds
     */
    public function __construct(
        public readonly array $shopImportIds,
        public readonly ?int $adminUserId = null,
    ) {
    }

    public function handle(BulkMarketplaceShopImportService $bulkMarketplaceShopImportService): void
    {
        $bulkMarketplaceShopImportService->importTrackedShopUrlsSequentially($this->shopImportIds);
    }

    public function failed(Throwable $exception): void
    {
        MarketplaceShopImport::query()
            ->whereIn('id', $this->shopImportIds)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed',
                'error' => 'Shop import job failed: '.$exception->getMessage(),
                'metadata' => json_encode([
                    'failed_stage' => 'queue_job',
                    'exception_class' => $exception::class,
                ]),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
