<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImportedProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessImportedProductMedia implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 21600;

    public int $tries = 1;

    public bool $failOnTimeout = false;

    public function __construct(
        public readonly int $productId,
    ) {
    }

    /**
     * Serialize imported product media so one product finishes before the next starts.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('imported-product-media-serial'))->releaseAfter(60),
        ];
    }

    public function handle(ImportedProductSyncService $importedProductSyncService): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $sourcePayload = $product->source_payload;

        if (! is_array($sourcePayload) || $sourcePayload === []) {
            return;
        }

        $importedProductSyncService->processSequentially($product, $sourcePayload);
    }
}
