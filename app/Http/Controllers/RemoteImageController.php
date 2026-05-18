<?php

namespace App\Http\Controllers;

use App\Support\RemoteImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RemoteImageController extends Controller
{
    private const CACHE_DIRECTORY = 'remote-images';

    public function __invoke(Request $request): Response
    {
        $url = (string) $request->query('url', '');

        abort_unless(RemoteImage::shouldProxy($url), 404);

        $cachedImage = $this->cachedImage($url);

        if ($cachedImage !== null) {
            return response($cachedImage['body'], 200, $this->responseHeaders($cachedImage['content_type'], strlen($cachedImage['body'])));
        }

        $upstreamResponse = Http::withHeaders(RemoteImage::requestHeaders($url))
            ->timeout(20)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->get($url);

        if ($upstreamResponse->failed()) {
            abort(502, 'Unable to fetch remote image.');
        }

        $contentType = $upstreamResponse->header('Content-Type', 'application/octet-stream');

        if (! str_starts_with(strtolower($contentType), 'image/')) {
            abort(502, 'Remote asset is not an image.');
        }

        $body = $upstreamResponse->body();
        $this->storeCachedImage($url, $contentType, $body);

        return response($body, 200, $this->responseHeaders($contentType, strlen($body)));
    }

    /**
     * @return array{content_type:string,body:string}|null
     */
    private function cachedImage(string $url): ?array
    {
        $basePath = $this->cacheBasePath($url);
        $metaPath = $basePath.'.json';
        $bodyPath = $basePath.'.bin';

        if (! Storage::disk('local')->exists($metaPath) || ! Storage::disk('local')->exists($bodyPath)) {
            return null;
        }

        $metadata = json_decode((string) Storage::disk('local')->get($metaPath), true);
        $contentType = is_array($metadata) && is_string($metadata['content_type'] ?? null)
            ? $metadata['content_type']
            : 'application/octet-stream';

        if (! str_starts_with(strtolower($contentType), 'image/')) {
            return null;
        }

        return [
            'content_type' => $contentType,
            'body' => (string) Storage::disk('local')->get($bodyPath),
        ];
    }

    private function storeCachedImage(string $url, string $contentType, string $body): void
    {
        $basePath = $this->cacheBasePath($url);

        Storage::disk('local')->put($basePath.'.json', json_encode([
            'url' => $url,
            'content_type' => $contentType,
            'cached_at' => now()->toIso8601String(),
        ]));
        Storage::disk('local')->put($basePath.'.bin', $body);
    }

    private function cacheBasePath(string $url): string
    {
        return self::CACHE_DIRECTORY.'/'.Str::of(hash('sha256', $url))->substr(0, 2).'/'.hash('sha256', $url);
    }

    /**
     * @return array<string, string>
     */
    private function responseHeaders(string $contentType, ?int $contentLength = null): array
    {
        $headers = [
            'Cache-Control' => 'public, max-age=604800, s-maxage=604800, immutable',
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="remote-image"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($contentLength !== null) {
            $headers['Content-Length'] = (string) $contentLength;
        }

        return $headers;
    }
}
