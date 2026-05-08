<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FogotProductMediaService
{
    /**
     * @return array{mime_type:string,data:string,preview_url:string,source_url:string}|null
     */
    public function redrawMainImage(string $imageUrl): ?array
    {
        return $this->safeProcessMainImage($imageUrl);
    }

    /**
     * @return array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>
     */
    public function translateImage(string $imageUrl): array
    {
        return $this->safeProcessDetailImages([$imageUrl]);
    }

    public function classifyImage(string $imageUrl): ?string
    {
        return $this->safeClassifyCategory($imageUrl);
    }

    /**
     * @param  array<int, string>  $galleryImageUrls
     * @param  array<int, string>  $descriptionImageUrls
     * @return array{
     *   main_image: array{mime_type:string,data:string,preview_url:string,source_url:string}|null,
     *   gallery_images: array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>,
     *   description_images: array<int, array{mime_type:string,data:string,preview_url:string,source_url:string}>,
     *   category: string|null
     * }
     */
    public function processImportPreview(?string $mainImageUrl, array $galleryImageUrls, array $descriptionImageUrls = []): array
    {
        $mainImage = $mainImageUrl ? $this->safeProcessMainImage($mainImageUrl) : null;
        $galleryImages = $this->safeProcessDetailImages($galleryImageUrls);
        $descriptionImages = $this->safeProcessDetailImages($descriptionImageUrls);
        $category = $mainImageUrl ? $this->safeClassifyCategory($mainImageUrl) : null;

        return [
            'main_image' => $mainImage,
            'gallery_images' => $galleryImages,
            'description_images' => $descriptionImages,
            'category' => $category,
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

    private function safeClassifyCategory(string $imageUrl): ?string
    {
        try {
            return $this->classifyCategory($imageUrl);
        } catch (Throwable $exception) {
            $this->logPreviewWarning('Fogot category classification failed during product preview.', [
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
        $mimeType = $this->guessMimeTypeFromUrl($imageUrl);

        $json = $this->postJson('/image/redraw', [
            'image_url' => $imageUrl,
            'mime_type' => $mimeType,
        ]);

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

            try {
                $mimeType = $this->guessMimeTypeFromUrl($imageUrl);
                $json = $this->postJson('/detail/image/translate', [
                    'image_url' => $imageUrl,
                    'mime_type' => $mimeType,
                ]);

                foreach ($this->extractImagePayloads($json) as $image) {
                    $processedImages[] = $this->formatImagePayload($imageUrl, $image['mime_type'], $image['data']);
                }
            } catch (Throwable $exception) {
                $this->logPreviewWarning('Fogot detail image translation failed for a single image.', [
                    'image_url' => $imageUrl,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return array_values($processedImages);
    }

    private function classifyCategory(string $imageUrl): ?string
    {
        $mimeType = $this->guessMimeTypeFromUrl($imageUrl);

        $json = $this->postJson('/detail/image/classify', [
            'image_url' => $imageUrl,
            'mime_type' => $mimeType,
        ]);

        $category = data_get($json, 'category')
            ?? data_get($json, 'body.category')
            ?? data_get($json, 'data.category');

        return is_string($category) && trim($category) !== '' ? trim($category) : null;
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

        $response = Http::acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry($retryTimes, $retrySleepMs)
            ->post(rtrim((string) config('services.fogot.base_url'), '/').$path, $payload);

        if ($response->failed()) {
            throw new RuntimeException("Fogot API request failed for [{$path}] with status {$response->status()}.");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Fogot API returned invalid JSON for [{$path}].");
        }

        return $json;
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
