<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImportedProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportedProductMedia implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $productId,
    ) {
    }

    public function handle(ImportedProductSyncService $importedProductSyncService): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $importedProductSyncService->process($product);
    }
}
