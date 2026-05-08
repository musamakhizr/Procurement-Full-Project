<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImportedProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ClassifyImportedProductCategory implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 180;

    public int $tries = 1;

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

        $importedProductSyncService->classifyCategory($product, $this->imageUrl);
    }
}
