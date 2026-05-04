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
     * @param  array<int, string>  $galleryUrls
     * @param  array<int, string>  $descriptionUrls
     */
    public function syncProductImages(Product $product, array $galleryUrls, array $descriptionUrls = []): void
    {
        $normalizedGalleryUrls = $this->normalizeUrls($galleryUrls);
        $normalizedDescriptionUrls = $this->normalizeUrls($descriptionUrls);

        if ($normalizedGalleryUrls->isEmpty() && $normalizedDescriptionUrls->isEmpty()) {
            return;
        }

        $downloadedGalleryImages = $normalizedGalleryUrls
            ->map(fn (string $url, int $index) => $this->downloadImage($product, $url, $index, 'gallery'))
            ->filter()
            ->values();

        $downloadedDescriptionImages = $normalizedDescriptionUrls
            ->map(fn (string $url, int $index) => $this->downloadImage($product, $url, $index, 'description'))
            ->filter()
            ->values();

        if ($downloadedGalleryImages->isEmpty() && $downloadedDescriptionImages->isEmpty()) {
            throw new RuntimeException('Unable to download product images from the source marketplace.');
        }

        $this->deleteExistingImages($product);

        $product->productImages()->delete();
        $product->productImages()->createMany([
            ...$downloadedGalleryImages->all(),
            ...$downloadedDescriptionImages->all(),
        ]);

        $primaryImage = $downloadedGalleryImages->first() ?? $downloadedDescriptionImages->first();

        $product->forceFill([
            'image_url' => $primaryImage['path'],
            'source_image_url' => $primaryImage['source_url'],
        ])->save();
    }

    /**
     * @param  array<int, string>  $urls
     * @return Collection<int, string>
     */
    private function normalizeUrls(array $urls): Collection
    {
        return collect($urls)
            ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
            ->map(fn (string $url) => trim($url))
            ->unique()
            ->values();
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

    private function downloadImage(Product $product, string $url, int $index, string $section): ?array
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
        $directory = 'products/'.$product->getKey().'/'.$section;
        $filename = sprintf('%02d-%s.%s', $index + 1, Str::random(12), $extension);
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return [
            'path' => $path,
            'source_url' => $url,
            'section' => $section,
            'sort_order' => $index,
            'is_primary' => $section === 'gallery' && $index === 0,
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
