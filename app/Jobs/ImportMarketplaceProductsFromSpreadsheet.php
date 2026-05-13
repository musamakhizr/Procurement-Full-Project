<?php

namespace App\Jobs;

use App\Services\BulkMarketplaceProductImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ImportMarketplaceProductsFromSpreadsheet implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 21600;

    public int $tries = 1;

    public bool $failOnTimeout = false;

    /**
     * @param array<int,string> $links
     */
    public function __construct(
        public readonly array $links,
        public readonly ?int $adminUserId = null,
    ) {
    }

    /**
     * Keep spreadsheet imports serialized even if multiple admins upload files.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('marketplace-spreadsheet-import'))->releaseAfter(60),
        ];
    }

    public function handle(BulkMarketplaceProductImportService $bulkMarketplaceProductImportService): void
    {
        $bulkMarketplaceProductImportService->importLinksSequentially($this->links);
    }
}
