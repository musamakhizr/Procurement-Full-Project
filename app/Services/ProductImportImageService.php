<?php

namespace App\Services;

use App\Models\Product;
use App\Support\RemoteImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImportImageService
{
    /**
     * @param  array<int, string>  $sourceUrls
     */
    public function syncProductImages(Product $product, array $sourceUrls): void
    {
        $normalizedUrls = collect($sourceUrls)
            ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
            ->map(fn (string $url) => trim($url))
            ->unique()
            ->values();

        if ($normalizedUrls->isEmpty()) {
            return;
        }

        $downloadedImages = $normalizedUrls
            ->map(fn (string $url, int $index) => $this->downloadImage($product, $url, $index))
            ->filter()
            ->values();

        if ($downloadedImages->isEmpty()) {
            throw new RuntimeException('Unable to download product images from the source marketplace.');
        }

        $this->deleteExistingImages($product);

        $product->productImages()->delete();
        $product->productImages()->createMany($downloadedImages->all());

        $primaryImage = $downloadedImages->first();

        $product->forceFill([
            'image_url' => $primaryImage['path'],
            'source_image_url' => $primaryImage['source_url'],
        ])->save();
    }

    private function deleteExistingImages(Product $product): void
    {
        $paths = $product->productImages()
            ->pluck('path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function downloadImage(Product $product, string $url, int $index): ?array
    {
        $response = Http::withHeaders(RemoteImage::requestHeaders($url))
            ->timeout(30)
            ->connectTimeout(15)
            ->retry(2, 500)
            ->get($url);

        if ($response->failed()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));

        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $extension = $this->resolveExtension($contentType, $url);
        $directory = 'products/'.$product->getKey();
        $filename = sprintf('%02d-%s.%s', $index + 1, Str::random(12), $extension);
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return [
            'path' => $path,
            'source_url' => $url,
            'sort_order' => $index,
            'is_primary' => $index === 0,
        ];
    }

    private function resolveExtension(string $contentType, string $url): string
    {
        $mimeExtension = match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => null,
        };

        if ($mimeExtension !== null) {
            return $mimeExtension;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'jpg';
    }
}
