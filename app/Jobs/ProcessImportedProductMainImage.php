<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImportedProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportedProductMainImage implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 660;

    public int $tries = 3;

    public array $backoff = [600, 600];

    public bool $failOnTimeout = false;

    public function __construct(
        public readonly int $productId,
        public readonly string $imageUrl,
    ) {
    }

    public function handle(ImportedProductSyncService $importedProductSyncService): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $importedProductSyncService->processMainImage($product, $this->imageUrl, true);
    }

    public function failed(Throwable $exception): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        app(ImportedProductSyncService::class)->recordMainImageFailure($product, $this->imageUrl, $exception);
    }
}
