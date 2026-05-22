<?php

namespace App\Services;

use App\Exceptions\TransientFogotApiException;
use App\Support\RemoteImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FogotProductMediaService
{
    private const DESCRIPTION_IMAGE_ALLOWED_CATEGORY = '介绍商品';

    private const DESCRIPTION_IMAGE_ALLOWED_CATEGORY_MOJIBAKE = 'ä»‹ç»å•†å“';

    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}|null
     */
    public function redrawMainImage(string $imageUrl): ?array
    {
        return $this->processMainImage($imageUrl);
    }

    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}|null
     */
    public function redrawImage(string $imageUrl): ?array
    {
        return $this->processMainImage($imageUrl);
    }

    /**
     * @return array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>
     */
    public function translateImage(string $imageUrl): array
    {
        return $this->processDetailImages([$imageUrl]);
    }

    public function classifyDescriptionImage(string $imageUrl): ?string
    {
        return $this->normalizeCategoryLabel($this->classifyDetailImageCategory($imageUrl));
    }

    public function shouldKeepDescriptionImage(string $imageUrl): bool
    {
        return $this->classifyDescriptionImage($imageUrl) === self::DESCRIPTION_IMAGE_ALLOWED_CATEGORY;
    }

    /**
     * @return array<string, scalar>|null
     */
    public function classifyProductCategory(string $productText, array $item): ?array
    {
        return $this->classifyProductCategoryLabel($productText, $item);
    }

    /**
     * @param  array<int, string>  $galleryImageUrls
     * @param  array<int, string>  $descriptionImageUrls
     * @return array{
     *   main_image: array{mime_type:string,data:string,preview_url:string,source_url:string}|null,
     *   gallery_images: array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>,
     *   description_images: array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>,
     *   category: array<string, scalar>|null
     * }
     */
    public function processImportPreview(?string $mainImageUrl, array $galleryImageUrls, array $descriptionImageUrls = []): array
    {
        $mainImage = $mainImageUrl ? $this->safeProcessMainImage($mainImageUrl) : null;
        $galleryImages = $this->safeProcessDetailImages($galleryImageUrls);
        $descriptionImages = $this->safeProcessDetailImages($descriptionImageUrls);

        return [
            'main_image' => $mainImage,
            'gallery_images' => $galleryImages,
            'description_images' => $descriptionImages,
            'category' => null,
        ];
    }

    /**
     * @param  array<int, string>  $imageUrls
     * @return array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>
     */
    private function safeProcessDetailImages(array $imageUrls): array
    {
        try {
            return $this->processDetailImages($imageUrls);
        } catch (Throwable $exception) {
            $this->logPreviewWarning('Fogot detail image translation failed during product preview.', [
                'image_urls' => $imageUrls,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}|null
     */
    private function safeProcessMainImage(string $imageUrl): ?array
    {
        try {
            return $this->processMainImage($imageUrl);
        } catch (Throwable $exception) {
            $this->logPreviewWarning('Fogot main image redraw failed during product preview.', [
                'image_url' => $imageUrl,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}|null
     */
    private function processMainImage(string $imageUrl): ?array
    {
        $json = $this->postJson('/image/redraw', $this->imageRequestPayload($imageUrl));

        $image = $this->extractFirstImagePayload($json, $imageUrl);

        return $image ? $this->formatImagePayload($imageUrl, $image['mime_type'], $image['data']) : null;
    }

    /**
     * @param  array<int, string>  $imageUrls
     * @return array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>
     */
    private function processDetailImages(array $imageUrls): array
    {
        $processedImages = [];

        foreach ($imageUrls as $imageUrl) {
            if (! is_string($imageUrl) || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                continue;
            }

            $json = $this->postJson('/detail/image/translate', $this->imageRequestPayload($imageUrl));

            foreach ($this->extractImagePayloads($json) as $image) {
                $processedImages[] = $this->formatImagePayload($imageUrl, $image['mime_type'], $image['data']);
            }
        }

        return array_values($processedImages);
    }

    private function classifyDetailImageCategory(string $imageUrl): ?string
    {
        $json = $this->postJson('/detail/image/classify', $this->imageRequestPayload($imageUrl));

        $category = data_get($json, 'category')
            ?? data_get($json, 'body.category')
            ?? data_get($json, 'data.category')
            ?? data_get($json, 'detail')
            ?? data_get($json, 'body.detail')
            ?? data_get($json, 'data.detail');

        return is_string($category) && trim($category) !== '' ? trim($category) : null;
    }

    /**
     * @return array<string, scalar>|null
     */
    private function classifyProductCategoryLabel(string $productText, array $item): ?array
    {
        $json = $this->postJson('/category/classify', [
            'product_text' => $productText,
            'items' => [[
                'number' => 0,
                'item_name' => (string) ($item['item_name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'picture' => (string) ($item['picture'] ?? ''),
                'delivery_date' => (string) ($item['delivery_date'] ?? ''),
                'note' => (string) ($item['note'] ?? ''),
                'link' => (string) ($item['link'] ?? ''),
            ]],
            'category_dict_text' => (string) config('services.fogot.category_dict_text', ''),
        ]);

        $firstItem = data_get($json, 'items.0')
            ?? data_get($json, 'body.items.0')
            ?? data_get($json, 'data.items.0');

        if (! is_array($firstItem)) {
            return null;
        }

        $category = collect($firstItem)
            ->filter(function ($value) {
                if (is_string($value)) {
                    return trim($value) !== '';
                }

                return is_int($value) || is_float($value) || is_bool($value);
            })
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        return $category !== [] ? $category : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $timeout = max((int) config('services.fogot.timeout', 30), 5);
        $connectTimeout = max((int) config('services.fogot.connect_timeout', 10), 3);
        $retryTimes = max((int) config('services.fogot.retry_times', 0), 0);
        $retrySleepMs = max((int) config('services.fogot.retry_sleep_ms', 500), 0);
        $retryStatuses = collect(config('services.fogot.retry_statuses', []))
            ->map(fn ($status) => (int) $status)
            ->filter()
            ->values()
            ->all();

        $attempts = $retryTimes + 1;
        $response = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->throttleFogotImageRequest($path);

                $response = Http::acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->post(rtrim((string) config('services.fogot.base_url'), '/').$path, $payload);

                if (! in_array($response->status(), $retryStatuses, true)) {
                    break;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            if ($attempt < $attempts && $retrySleepMs > 0) {
                usleep($retrySleepMs * 1000);
            }
        }

        if ($response === null) {
            throw new TransientFogotApiException(
                path: $path,
                status: null,
                attempts: $attempts,
                message: "Fogot API request failed for [{$path}] after {$attempts} attempt(s): {$lastException?->getMessage()}",
                previous: $lastException,
            );
        }

        if ($response->failed()) {
            $body = $response->json() ?? $response->body();
            $message = "Fogot API request failed for [{$path}] with status {$response->status()} after {$attempts} attempt(s): ".Str::limit(json_encode($body), 500);

            if (in_array($response->status(), $retryStatuses, true)) {
                throw new TransientFogotApiException(
                    path: $path,
                    status: $response->status(),
                    attempts: $attempts,
                    message: $message,
                );
            }

            throw new RuntimeException($message);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Fogot API returned invalid JSON for [{$path}].");
        }

        return $json;
    }

    /**
     * @return array{image_url:string,mime_type:string,image_base64?:string}
     */
    private function imageRequestPayload(string $imageUrl): array
    {
        $payload = [
            'image_url' => $imageUrl,
            'mime_type' => $this->guessMimeTypeFromUrl($imageUrl),
        ];

        if (! (bool) config('services.fogot.remote_image_cache_enabled', true)) {
            return $payload;
        }

        if (! (bool) config('services.fogot.send_image_base64', true)) {
            return $payload;
        }

        $cachedImage = $this->cachedRemoteImage($imageUrl);

        if ($cachedImage === null) {
            return $payload;
        }

        return [
            ...$payload,
            'mime_type' => $cachedImage['mime_type'],
            'image_base64' => base64_encode($cachedImage['binary']),
        ];
    }

    /**
     * @return array{binary:string,mime_type:string}|null
     */
    private function cachedRemoteImage(string $imageUrl): ?array
    {
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $cacheKey = sha1($imageUrl);
        $directory = trim((string) config('services.fogot.remote_image_cache_directory', 'fogot-image-cache'), '/');
        $metaPath = "{$directory}/{$cacheKey}.json";

        if (Storage::disk('local')->exists($metaPath)) {
            $metadata = json_decode((string) Storage::disk('local')->get($metaPath), true);
            $path = is_array($metadata) ? ($metadata['path'] ?? null) : null;
            $mimeType = is_array($metadata) ? ($metadata['mime_type'] ?? null) : null;

            if (is_string($path) && is_string($mimeType) && Storage::disk('local')->exists($path)) {
                return [
                    'binary' => (string) Storage::disk('local')->get($path),
                    'mime_type' => $mimeType,
                ];
            }
        }

        $this->throttleRemoteImageDownload();

        try {
            $response = Http::withHeaders(RemoteImage::requestHeaders($imageUrl))
                ->timeout(60)
                ->connectTimeout(20)
                ->retry(1, 1500)
                ->get($imageUrl);
        } catch (Throwable $exception) {
            Log::warning('Unable to pre-cache marketplace image before Fogot request.', [
                'image_url' => $imageUrl,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Marketplace image pre-cache returned a failed response.', [
                'image_url' => $imageUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $mimeType = strtolower(trim((string) $response->header('Content-Type', '')));

        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $binary = $response->body();
        $extension = $this->extensionFromMimeType($mimeType);
        $imagePath = "{$directory}/{$cacheKey}.{$extension}";

        Storage::disk('local')->put($imagePath, $binary);
        Storage::disk('local')->put($metaPath, json_encode([
            'source_url' => $imageUrl,
            'path' => $imagePath,
            'mime_type' => $mimeType,
            'cached_at' => now()->toIso8601String(),
        ]));

        return [
            'binary' => $binary,
            'mime_type' => $mimeType,
        ];
    }

    private function throttleFogotImageRequest(string $path): void
    {
        if (! in_array($path, ['/image/redraw', '/detail/image/translate', '/detail/image/classify'], true)) {
            return;
        }

        $this->throttle('fogot:image-request:last-at', 'fogot:image-request:lock', (int) config('services.fogot.image_request_delay_ms', 0));
    }

    private function throttleRemoteImageDownload(): void
    {
        $this->throttle('fogot:remote-image-download:last-at', 'fogot:remote-image-download:lock', (int) config('services.fogot.remote_image_download_delay_ms', 0));
    }

    private function throttle(string $timestampKey, string $lockKey, int $delayMs): void
    {
        if ($delayMs <= 0 || app()->runningUnitTests()) {
            return;
        }

        Cache::lock($lockKey, 30)->block(30, function () use ($timestampKey, $delayMs) {
            $lastAt = (float) Cache::get($timestampKey, 0);
            $elapsedMs = $lastAt > 0 ? (int) ((microtime(true) - $lastAt) * 1000) : $delayMs;
            $remainingMs = $delayMs - $elapsedMs;

            if ($remainingMs > 0) {
                usleep($remainingMs * 1000);
            }

            Cache::put($timestampKey, microtime(true), now()->addMinutes(10));
        });
    }

    private function extensionFromMimeType(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    /**
     * @return array{mime_type:string,data:string}|null
     */
    private function extractFirstImagePayload(array $payload, string $sourceUrl): ?array
    {
        $images = $this->extractImagePayloads($payload);

        if ($images !== []) {
            return $images[0];
        }

        $rawData = data_get($payload, 'data')
            ?? data_get($payload, 'body.data');

        if (is_string($rawData) && trim($rawData) !== '') {
            return [
                'mime_type' => $this->guessMimeTypeFromUrl($sourceUrl),
                'data' => trim($rawData),
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{mime_type:string,data:string}>
     */
    private function extractImagePayloads(array $payload): array
    {
        $candidates = data_get($payload, 'images')
            ?? data_get($payload, 'body.images')
            ?? data_get($payload, 'data.images');

        if (! is_array($candidates)) {
            return [];
        }

        return collect($candidates)
            ->map(function ($image) {
                if (! is_array($image)) {
                    return null;
                }

                $data = $image['data'] ?? null;
                $mimeType = $image['mime_type'] ?? null;

                if (! is_string($data) || trim($data) === '') {
                    return null;
                }

                return [
                    'mime_type' => is_string($mimeType) && trim($mimeType) !== '' ? trim($mimeType) : 'image/jpeg',
                    'data' => trim($data),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}
     */
    private function formatImagePayload(string $sourceUrl, string $mimeType, string $base64Data): array
    {
        return [
            'mime_type' => $mimeType,
            'data' => $base64Data,
            'preview_url' => "data:{$mimeType};base64,{$base64Data}",
            'source_url' => $sourceUrl,
        ];
    }

    private function normalizeCategoryLabel(?string $category): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $normalized = trim($category);

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            self::DESCRIPTION_IMAGE_ALLOWED_CATEGORY_MOJIBAKE => self::DESCRIPTION_IMAGE_ALLOWED_CATEGORY,
            default => $normalized,
        };
    }

    private function guessMimeTypeFromUrl(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logPreviewWarning(string $message, array $context): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Log::warning($message, $context);
    }
}
