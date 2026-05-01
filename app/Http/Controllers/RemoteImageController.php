<?php

namespace App\Http\Controllers;

use App\Support\RemoteImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class RemoteImageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $url = (string) $request->query('url', '');

        abort_unless(RemoteImage::shouldProxy($url), 404);

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

        $headers = [
            'Cache-Control' => 'public, max-age=86400, s-maxage=604800',
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="remote-image"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($upstreamResponse->header('Content-Length')) {
            $headers['Content-Length'] = $upstreamResponse->header('Content-Length');
        }

        return response($upstreamResponse->body(), 200, $headers);
    }
}
