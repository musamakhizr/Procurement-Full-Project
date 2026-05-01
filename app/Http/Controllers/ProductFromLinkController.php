<?php

namespace App\Http\Controllers;

use App\Support\RemoteImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductFromLinkController extends Controller
{
    private string $apiKey = 't7100';
    private string $apiSecret = '7100fb80';

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'link' => ['required', 'string', 'max:2000'],
        ]);

        $link = trim($validated['link']);
        $numIid = null;
        $platform = $this->detectPlatform($link, $numIid);

        if (blank($numIid)) {
            return response()->json([
                'message' => 'Unable to detect a valid product id from the provided link.',
            ], 422);
        }

        $payload = $this->fetchItemPayload($platform, $numIid);
        $item = $payload['item'] ?? $payload['data']['item'] ?? $payload;

        if (! is_array($item) || $item === []) {
            return response()->json([
                'message' => 'The upstream API did not return a usable product payload.',
                'platform' => $platform,
                'num_iid' => $numIid,
            ], 502);
        }

        return response()->json([
            'platform' => $platform,
            'num_iid' => $numIid,
            'product' => $this->normalizeItem($item, $platform, $numIid, $link),
            'raw' => $item,
        ]);
    }

    private function detectPlatform(string $link, ?string &$numIid): string
    {
        $normalizedLink = Str::lower($link);

        if (str_contains($normalizedLink, '1688.com')) {
            preg_match('/offer\/(\d+)\.html/i', $link, $matches);
            $numIid = $matches[1] ?? $numIid;

            return '1688';
        }

        if (str_contains($normalizedLink, 'jd.com')) {
            preg_match('/item\.jd\.com\/(\d+)\.html/i', $link, $matches);
            $numIid = $matches[1] ?? $numIid;

            return 'jd';
        }

        if (str_contains($normalizedLink, 'taobao.com') || str_contains($normalizedLink, 'tmall.com')) {
            preg_match('/[?&]id=(\d+)/i', $link, $matches);
            $numIid = $matches[1] ?? $numIid;

            return 'taobao';
        }

        preg_match('/(\d{6,})/', $link, $matches);
        $numIid = $matches[1] ?? $numIid;

        return 'taobao';
    }

    private function fetchItemPayload(string $platform, string $numIid): array
    {
        $endpoint = match ($platform) {
            '1688' => '1688/item_get/',
            'jd' => 'jd/item_get/',
            default => 'taobao/item_get_pro/',
        };

        $response = Http::acceptJson()
            ->timeout(60)
            ->connectTimeout(30)
            ->retry(2, 500)
            ->get("https://api-gw.onebound.cn/{$endpoint}", [
                'key' => $this->apiKey,
                'secret' => $this->apiSecret,
                'num_iid' => $numIid,
                'lang' => 'zh-CN',
                'cache' => 'no',
            ]);

        if ($response->failed()) {
            abort(response()->json([
                'message' => 'Failed to fetch product details from the upstream API.',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ], 502));
        }

        $json = $response->json();

        if (! is_array($json)) {
            abort(response()->json([
                'message' => 'The upstream API returned an invalid JSON payload.',
            ], 502));
        }

        return $json;
    }

    private function normalizeItem(array $item, string $platform, string $numIid, string $fallbackLink): array
    {
        $title = $item['title']
            ?? $item['name']
            ?? $item['item_name']
            ?? 'Untitled product';

        $price = $item['original_price']
            ?? $item['price']
            ?? $item['promotion_price']
            ?? $item['reserve_price']
            ?? null;

        $detailUrl = $item['detail_url']
            ?? $item['item_url']
            ?? $item['url']
            ?? $fallbackLink;

        $imageUrl = $item['pic_url']
            ?? $item['main_pic']
            ?? $item['image']
            ?? $item['img']
            ?? data_get($item, 'images.0')
            ?? data_get($item, 'item_imgs.0.url')
            ?? data_get($item, 'item_imgs.0')
            ?? null;

        $imageUrl = $this->normalizeUrl($imageUrl);

        return [
            'title' => $title,
            'original_price' => $this->normalizePrice($price),
            'detail_url' => $this->normalizeUrl($detailUrl),
            'image_url' => $imageUrl,
            'display_image_url' => RemoteImage::proxiedUrl($imageUrl),
            'platform' => $platform,
            'num_iid' => $numIid,
        ];
    }

    private function normalizeUrl(null|string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (Str::startsWith($value, '//')) {
            return 'https:'.$value;
        }

        return $value;
    }

    private function normalizePrice(mixed $price): ?string
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (is_numeric($price)) {
            return number_format((float) $price, 2, '.', '');
        }

        return (string) $price;
    }
}
