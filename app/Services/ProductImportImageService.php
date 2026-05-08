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
    public function resetProductImages(Product $product): void
    {
        $fallbackImageUrl = $product->source_image_url
            ?? data_get($product->source_payload, 'main_image_url')
            ?? data_get($product->source_payload, 'image_url');

        $this->deleteExistingImages($product);
        $product->productImages()->delete();

        $product->forceFill([
            'image_url' => $fallbackImageUrl,
        ])->save();
    }

    /**
     * @param  array{mime_type:string,data:string,source_url:?string}  $image
     */
    public function appendProcessedImage(Product $product, array $image, int $index, string $section): bool
    {
        $storedImage = $this->storeProcessedImage($product, $image, $index, $section);

        if ($storedImage === null) {
            return false;
        }

        $this->persistStoredImage($product, $storedImage);

        return true;
    }

    public function appendRemoteImage(Product $product, string $url, int $index, string $section): bool
    {
        $storedImage = $this->downloadImage($product, $url, $index, $section);

        if ($storedImage === null) {
            return false;
        }

        $this->persistStoredImage($product, $storedImage);

        return true;
    }

    /**
     * @param  array<int, array{type:string,mime_type?:string,data?:string,source_url?:string,url?:string}>  $galleryImages
     * @param  array<int, array{type:string,mime_type?:string,data?:string,source_url?:string,url?:string}>  $descriptionImages
     */
    public function syncMixedProductImages(Product $product, array $galleryImages, array $descriptionImages = []): void
    {
        $savedGalleryImages = collect($galleryImages)
            ->values()
            ->map(fn (array $image, int $index) => $this->storeMixedImage($product, $image, $index, 'gallery'))
            ->filter()
            ->values();

        $savedDescriptionImages = collect($descriptionImages)
            ->values()
            ->map(fn (array $image, int $index) => $this->storeMixedImage($product, $image, $index, 'description'))
            ->filter()
            ->values();

        if ($savedGalleryImages->isEmpty() && $savedDescriptionImages->isEmpty()) {
            throw new RuntimeException('Unable to store any product images from the import payload.');
        }

        $this->deleteExistingImages($product);

        $product->productImages()->delete();
        $product->productImages()->createMany([
            ...$savedGalleryImages->all(),
            ...$savedDescriptionImages->all(),
        ]);

        $primaryImage = $savedGalleryImages->first() ?? $savedDescriptionImages->first();

        $product->forceFill([
            'image_url' => $primaryImage['path'],
            'source_image_url' => $primaryImage['source_url'],
        ])->save();
    }

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
     * @param  array{mime_type?:string,data?:string,source_url?:string}|null  $mainImage
     * @param  array<int, array{mime_type?:string,data?:string,source_url?:string}>  $galleryImages
     * @param  array<int, array{mime_type?:string,data?:string,source_url?:string}>  $descriptionImages
     */
    public function syncProcessedProductImages(Product $product, ?array $mainImage, array $galleryImages = [], array $descriptionImages = []): void
    {
        $normalizedMainImage = $this->normalizeProcessedImage($mainImage);
        $normalizedGalleryImages = $this->normalizeProcessedImages($galleryImages);
        $normalizedDescriptionImages = $this->normalizeProcessedImages($descriptionImages);

        if ($normalizedMainImage !== null && $normalizedGalleryImages->every(fn (array $image) => ! $this->sameProcessedImage($image, $normalizedMainImage))) {
            $normalizedGalleryImages->prepend($normalizedMainImage);
        }

        if ($normalizedGalleryImages->isEmpty() && $normalizedDescriptionImages->isEmpty()) {
            return;
        }

        $savedGalleryImages = $normalizedGalleryImages
            ->values()
            ->map(fn (array $image, int $index) => $this->storeProcessedImage($product, $image, $index, 'gallery'))
            ->filter()
            ->values();

        $savedDescriptionImages = $normalizedDescriptionImages
            ->values()
            ->map(fn (array $image, int $index) => $this->storeProcessedImage($product, $image, $index, 'description'))
            ->filter()
            ->values();

        if ($savedGalleryImages->isEmpty() && $savedDescriptionImages->isEmpty()) {
            throw new RuntimeException('Unable to decode and store processed product images.');
        }

        $this->deleteExistingImages($product);

        $product->productImages()->delete();
        $product->productImages()->createMany([
            ...$savedGalleryImages->all(),
            ...$savedDescriptionImages->all(),
        ]);

        $primaryImage = $savedGalleryImages->first() ?? $savedDescriptionImages->first();

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

    /**
     * @param  array{path:string,source_url:?string,section:string,sort_order:int,is_primary:bool}  $storedImage
     */
    private function persistStoredImage(Product $product, array $storedImage): void
    {
        $existingImage = $product->productImages()
            ->where('section', $storedImage['section'])
            ->where('sort_order', $storedImage['sort_order'])
            ->first();

        if ($existingImage && $existingImage->path !== $storedImage['path']) {
            Storage::disk('public')->delete($existingImage->path);
        }

        $product->productImages()->updateOrCreate(
            [
                'section' => $storedImage['section'],
                'sort_order' => $storedImage['sort_order'],
            ],
            $storedImage,
        );

        $this->refreshPrimaryImage($product);
    }

    private function refreshPrimaryImage(Product $product): void
    {
        $primaryImage = $product->productImages()
            ->orderByRaw("case when section = 'gallery' then 0 else 1 end")
            ->orderBy('sort_order')
            ->first();

        if (! $primaryImage) {
            return;
        }

        $product->forceFill([
            'image_url' => $primaryImage->path,
            'source_image_url' => $primaryImage->source_url ?? $product->source_image_url,
        ])->save();
    }

    /**
     * @param  array<int, array{mime_type?:string,data?:string,source_url?:string}>  $images
     * @return Collection<int, array{mime_type:string,data:string,source_url:?string}>
     */
    private function normalizeProcessedImages(array $images): Collection
    {
        return collect($images)
            ->map(fn ($image) => is_array($image) ? $this->normalizeProcessedImage($image) : null)
            ->filter()
            ->values();
    }

    /**
     * @param  array{mime_type?:string,data?:string,source_url?:string}|null  $image
     * @return array{mime_type:string,data:string,source_url:?string}|null
     */
    private function normalizeProcessedImage(?array $image): ?array
    {
        if (! is_array($image)) {
            return null;
        }

        $data = $image['data'] ?? null;

        if (! is_string($data) || trim($data) === '') {
            return null;
        }

        $mimeType = $image['mime_type'] ?? 'image/jpeg';
        $sourceUrl = $image['source_url'] ?? null;

        return [
            'mime_type' => is_string($mimeType) && trim($mimeType) !== '' ? trim($mimeType) : 'image/jpeg',
            'data' => trim($data),
            'source_url' => is_string($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL) ? trim($sourceUrl) : null,
        ];
    }

    /**
     * @param  array{mime_type:string,data:string,source_url:?string}  $left
     * @param  array{mime_type:string,data:string,source_url:?string}  $right
     */
    private function sameProcessedImage(array $left, array $right): bool
    {
        return $left['data'] === $right['data']
            || ($left['source_url'] !== null && $left['source_url'] === $right['source_url']);
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

    /**
     * @param  array{type:string,mime_type?:string,data?:string,source_url?:string,url?:string}  $image
     */
    private function storeMixedImage(Product $product, array $image, int $index, string $section): ?array
    {
        return match ($image['type'] ?? null) {
            'processed' => $this->storeProcessedImage(
                $product,
                [
                    'mime_type' => (string) ($image['mime_type'] ?? 'image/jpeg'),
                    'data' => (string) ($image['data'] ?? ''),
                    'source_url' => is_string($image['source_url'] ?? null) ? $image['source_url'] : null,
                ],
                $index,
                $section,
            ),
            'remote' => is_string($image['url'] ?? null)
                ? $this->downloadImage($product, $image['url'], $index, $section)
                : null,
            default => null,
        };
    }

    /**
     * @param  array{mime_type:string,data:string,source_url:?string}  $image
     */
    private function storeProcessedImage(Product $product, array $image, int $index, string $section): ?array
    {
        $binary = $this->decodeBase64Image($image['data']);

        if ($binary === null) {
            return null;
        }

        $extension = $this->resolveExtensionFromMimeType($image['mime_type']);
        $directory = 'products/'.$product->getKey().'/'.$section;
        $filename = sprintf('%02d-%s.%s', $index + 1, Str::random(12), $extension);
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'source_url' => $image['source_url'],
            'section' => $section,
            'sort_order' => $index,
            'is_primary' => $section === 'gallery' && $index === 0,
        ];
    }

    private function decodeBase64Image(string $data): ?string
    {
        $normalized = preg_replace('/^data:[^;]+;base64,/i', '', trim($data));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        $decoded = base64_decode(str_replace(["\r", "\n", ' '], '', $normalized), true);

        return $decoded === false ? null : $decoded;
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

    private function resolveExtensionFromMimeType(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }
}
