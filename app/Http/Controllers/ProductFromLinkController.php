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

        $mainImageUrl = $this->extractMainImageUrl($item);
        $galleryImages = $this->extractGalleryImageUrls($item, $mainImageUrl);
        $descriptionImages = $this->extractDescriptionImageUrls($item);
        $variants = $this->extractVariants($item);
        $detailUrl = $this->normalizeUrl($detailUrl);
        $descriptionHtml = $this->normalizeDescriptionHtml($item['desc'] ?? null);
        $description = $this->buildDescription($item);

        return [
            'title' => $title,
            'original_price' => $this->normalizePrice($price),
            'detail_url' => $detailUrl,
            'description' => $description,
            'description_html' => $descriptionHtml,
            'main_image_url' => $mainImageUrl,
            'image_url' => $mainImageUrl,
            'display_image_url' => RemoteImage::proxiedUrl($mainImageUrl),
            'images' => $galleryImages,
            'display_images' => array_map(fn (string $url) => RemoteImage::proxiedUrl($url), $galleryImages),
            'description_images' => $descriptionImages,
            'display_description_images' => array_map(fn (string $url) => RemoteImage::proxiedUrl($url), $descriptionImages),
            'processed_main_image' => null,
            'processed_gallery_images' => [],
            'processed_description_images' => [],
            'classified_category' => null,
            'variants' => $variants,
            'platform' => $platform,
            'num_iid' => $numIid,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractGalleryImageUrls(array $item, ?string $mainImageUrl): array
    {
        $candidates = [
            ...$this->extractArrayImageUrls($item['images'] ?? []),
            ...$this->extractArrayImageUrls($item['item_imgs'] ?? []),
        ];

        return $this->normalizeExtractedUrls($candidates, [$mainImageUrl]);
    }

    /**
     * @return array<int, string>
     */
    private function extractDescriptionImageUrls(array $item): array
    {
        $candidates = [
            ...$this->extractArrayImageUrls($item['desc_img'] ?? []),
            ...$this->extractDescriptionHtmlImageUrls($item['desc'] ?? null),
        ];

        return $this->normalizeExtractedUrls($candidates);
    }

    private function extractMainImageUrl(array $item): ?string
    {
        $candidates = [
            $item['pic_url'] ?? null,
            $item['main_pic'] ?? null,
            $item['image'] ?? null,
            $item['img'] ?? null,
            ...$this->extractArrayImageUrls($item['item_imgs'] ?? []),
        ];

        return $this->normalizeExtractedUrls($candidates)[0] ?? null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     * @param  array<int, string|null>  $excludedUrls
     * @return array<int, string>
     */
    private function normalizeExtractedUrls(array $candidates, array $excludedUrls = []): array
    {
        $normalizedExcludedUrls = collect($excludedUrls)
            ->map(fn ($url) => is_string($url) ? $this->normalizeUrl($url) : null)
            ->filter()
            ->values()
            ->all();

        return collect($candidates)
            ->map(fn ($url) => is_string($url) ? $this->normalizeUrl($url) : null)
            ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && ! in_array($url, $normalizedExcludedUrls, true) && ! $this->shouldIgnoreImageUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractArrayImageUrls(array $images): array
    {
        return collect($images)
            ->map(function ($image) {
                if (is_string($image)) {
                    return $image;
                }

                if (is_array($image)) {
                    return $image['url'] ?? $image['image'] ?? $image['img'] ?? null;
                }

                return null;
            })
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractDescriptionHtmlImageUrls(?string $descriptionHtml): array
    {
        if (blank($descriptionHtml)) {
            return [];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionHtml, $matches);

        return array_values(array_filter($matches[1] ?? []));
    }

    private function normalizeDescriptionHtml(?string $descriptionHtml): ?string
    {
        return blank($descriptionHtml) ? null : trim($descriptionHtml);
    }

    /**
     * @return array<int, array{
     *   sku_id:string,
     *   properties_key:string|null,
     *   properties_name:string|null,
     *   label:string,
     *   image_url:string|null,
     *   price:float|null,
     *   original_price:float|null,
     *   stock_quantity:int,
     *   option_values:array<int, array{key:string,group_name:string,value:string}>
     * }>
     */
    private function extractVariants(array $item): array
    {
        $propertyImageMap = $this->extractPropertyImageMap($item);
        $skus = data_get($item, 'skus.sku', []);

        if (! is_array($skus)) {
            return [];
        }

        return collect($skus)
            ->map(function ($sku, int $index) use ($propertyImageMap, $item) {
                if (! is_array($sku)) {
                    return null;
                }

                $propertiesKey = filled($sku['properties'] ?? null) ? (string) $sku['properties'] : null;
                $propertiesName = filled($sku['properties_name'] ?? null)
                    ? (string) $sku['properties_name']
                    : (is_string($propertiesKey) ? data_get($item, 'props_list.'.$propertiesKey) : null);
                $optionValues = $this->parseVariantOptionValues($propertiesName);

                if ($optionValues === []) {
                    return null;
                }

                return [
                    'sku_id' => (string) ($sku['sku_id'] ?? $index),
                    'properties_key' => $propertiesKey,
                    'properties_name' => $propertiesName,
                    'label' => implode(' / ', array_column($optionValues, 'value')),
                    'image_url' => $this->resolveVariantImageUrl($optionValues, $propertyImageMap),
                    'price' => is_numeric($sku['price'] ?? null) ? (float) $sku['price'] : null,
                    'original_price' => is_numeric($sku['orginal_price'] ?? $sku['original_price'] ?? null) ? (float) ($sku['orginal_price'] ?? $sku['original_price']) : null,
                    'stock_quantity' => max((int) ($sku['quantity'] ?? 0), 0),
                    'option_values' => $optionValues,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function extractPropertyImageMap(array $item): array
    {
        $map = [];

        foreach ((array) ($item['props_img'] ?? []) as $key => $url) {
            $normalizedUrl = is_string($url) ? $this->normalizeUrl($url) : null;

            if (is_string($key) && $normalizedUrl && ! $this->shouldIgnoreImageUrl($normalizedUrl)) {
                $map[$key] = $normalizedUrl;
            }
        }

        foreach ((array) data_get($item, 'props_imgs.prop_img', []) as $image) {
            if (! is_array($image)) {
                continue;
            }

            $properties = $image['properties'] ?? null;
            $url = isset($image['url']) ? $this->normalizeUrl((string) $image['url']) : null;

            if (is_string($properties) && $url && ! $this->shouldIgnoreImageUrl($url)) {
                $map[$properties] = $url;
            }
        }

        foreach ((array) data_get($item, 'prop_imgs.prop_img', []) as $image) {
            if (! is_array($image)) {
                continue;
            }

            $properties = $image['properties'] ?? null;
            $url = isset($image['url']) ? $this->normalizeUrl((string) $image['url']) : null;

            if (is_string($properties) && $url && ! $this->shouldIgnoreImageUrl($url)) {
                $map[$properties] = $url;
            }
        }

        return $map;
    }

    /**
     * @return array<int, array{key:string,group_name:string,value:string}>
     */
    private function parseVariantOptionValues(?string $propertiesName): array
    {
        if (! is_string($propertiesName) || trim($propertiesName) === '') {
            return [];
        }

        return collect(explode(';', $propertiesName))
            ->map(function (string $segment) {
                $parts = array_map('trim', explode(':', $segment));

                if (count($parts) < 4) {
                    return null;
                }

                return [
                    'key' => $parts[0].':'.$parts[1],
                    'group_name' => $parts[2],
                    'value' => implode(':', array_slice($parts, 3)),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{key:string,group_name:string,value:string}>  $optionValues
     * @param  array<string, string>  $propertyImageMap
     */
    private function resolveVariantImageUrl(array $optionValues, array $propertyImageMap): ?string
    {
        foreach ($optionValues as $optionValue) {
            $url = $propertyImageMap[$optionValue['key']] ?? null;

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function shouldIgnoreImageUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if ($host === 'www.o0b.cn' && $path === '/i.php') {
            return true;
        }

        return Str::endsWith($path, '/spaceball.gif');
    }

    private function buildDescription(array $item): string
    {
        $shortDescription = $this->sanitizeDescriptionText((string) ($item['desc_short'] ?? ''));
        $htmlDescription = $this->sanitizeDescriptionText((string) ($item['desc'] ?? ''));

        $propertyLines = collect($item['props'] ?? [])
            ->filter(fn ($prop) => is_array($prop) && filled($prop['name'] ?? null) && filled($prop['value'] ?? null))
            ->map(fn (array $prop) => "{$prop['name']}: {$prop['value']}")
            ->values()
            ->all();

        return collect([
            $shortDescription,
            $htmlDescription,
            $propertyLines !== [] ? implode(PHP_EOL, $propertyLines) : null,
        ])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->implode(PHP_EOL.PHP_EOL);
    }

    private function sanitizeDescriptionText(string $value): string
    {
        $decoded = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect(preg_split('/\R+/u', $decoded) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s+/u', ' ', $line) ?? ''))
            ->filter(fn (string $line) => $line !== '' && ! $this->isNoiseDescriptionLine($line))
            ->unique()
            ->implode(PHP_EOL);
    }

    private function isNoiseDescriptionLine(string $line): bool
    {
        $normalizedLine = Str::lower($line);

        if (
            str_contains($normalizedLine, 'styletype')
            || str_contains($normalizedLine, 'usemap')
            || str_contains($normalizedLine, '&quot;')
        ) {
            return true;
        }

        if (preg_match('/^\{.*\}$/u', $line) === 1) {
            return true;
        }

        return false;
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
