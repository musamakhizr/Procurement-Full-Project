<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceShopProductFetchService
{
    private string $apiKey;

    private string $apiSecret;

    public function __construct(
        private readonly MarketplaceProductFetchService $marketplaceProductFetchService,
    ) {
        $this->apiKey = (string) config('services.onebound.key', 't7100');
        $this->apiSecret = (string) config('services.onebound.secret', '7100fb80');
    }

    /**
     * @return array{shop_id:string,seller_id:string,seller_nick:?string,seed_platform:string,seed_num_iid:string,seed_product_url:string,raw:array<string,mixed>}
     */
    public function resolveShopFromSeedProductUrl(string $productUrl): array
    {
        $result = $this->marketplaceProductFetchService->fetch($productUrl);
        $item = $result['raw'];
        $sellerId = data_get($item, 'seller_id');
        $shopId = data_get($item, 'shop_id');
        $sellerNick = data_get($item, 'seller_info.sid');

        if ($result['platform'] === '1688') {
            if (! is_scalar($sellerNick) || trim((string) $sellerNick) === '') {
                throw new RuntimeException('Seller nick sid was missing from the 1688 seed product response.');
            }

            $sellerId = is_scalar($sellerId) && trim((string) $sellerId) !== '' ? (string) $sellerId : (string) data_get($item, 'seller_info.user_num_id', '');
            $shopId = is_scalar($shopId) && trim((string) $shopId) !== '' ? (string) $shopId : $sellerId;

            return [
                'shop_id' => (string) $shopId,
                'seller_id' => (string) $sellerId,
                'seller_nick' => (string) $sellerNick,
                'seed_platform' => $result['platform'],
                'seed_num_iid' => $result['num_iid'],
                'seed_product_url' => $productUrl,
                'raw' => $item,
            ];
        }

        if (! is_scalar($sellerId) || trim((string) $sellerId) === '') {
            throw new RuntimeException('Seller id was missing from the seed product response.');
        }

        if (! is_scalar($shopId) || trim((string) $shopId) === '') {
            throw new RuntimeException('Shop id was missing from the seed product response.');
        }

        return [
            'shop_id' => (string) $shopId,
            'seller_id' => (string) $sellerId,
            'seller_nick' => null,
            'seed_platform' => $result['platform'],
            'seed_num_iid' => $result['num_iid'],
            'seed_product_url' => $productUrl,
            'raw' => $item,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function fetchProductLinksForShop(string $shopId, string $sellerId, string $platform = 'taobao', ?string $sellerNick = null): array
    {
        $links = [];
        $page = 1;
        $pageCount = 1;
        $maxPages = max(0, (int) config('services.onebound.shop_import_max_pages', 0));

        do {
            $pagePayload = $this->fetchProductPage($shopId, $sellerId, $page, $platform, $sellerNick);
            $itemsPayload = is_array($pagePayload['items'] ?? null) ? $pagePayload['items'] : [];
            $pageCount = max(1, (int) data_get($itemsPayload, 'page_count', $pageCount));

            foreach ($this->productLinksFromPage($itemsPayload, $platform) as $link) {
                $links[] = $link;
            }

            $page++;
        } while ($page <= $pageCount && ($maxPages === 0 || $page <= $maxPages));

        return collect($links)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchProductPage(string $shopId, string $sellerId, int $page, string $platform, ?string $sellerNick): array
    {
        $is1688 = $platform === '1688';
        $endpoint = $is1688
            ? 'https://api-gw.onebound.cn/1688/item_search_shop/'
            : 'https://api-gw.onebound.cn/taobao/item_search_shop_pro/';
        $query = $is1688
            ? [
                'key' => $this->apiKey,
                'secret' => $this->apiSecret,
                'seller_nick' => $sellerNick,
                'start_price' => 0,
                'end_price' => 0,
                'q' => '',
                'page' => $page,
                'cid' => '',
                'cache' => 'no',
                'lang' => 'zh-CN',
            ]
            : [
                'key' => $this->apiKey,
                'secret' => $this->apiSecret,
                'seller_id' => $sellerId,
                'shop_id' => $shopId,
                'page' => $page,
                'sort' => '',
                'lang' => 'zh-CN',
            ];

        if ($is1688 && (! is_string($sellerNick) || trim($sellerNick) === '')) {
            throw new RuntimeException('Seller nick is required to fetch 1688 shop products.');
        }

        $response = Http::acceptJson()
            ->timeout(90)
            ->connectTimeout(30)
            ->retry(2, 750)
            ->get($endpoint, $query);

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch shop products page {$page}. Status {$response->status()}: ".json_encode($response->json() ?? $response->body()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("The shop products API returned invalid JSON for page {$page}.");
        }

        $errorCode = (string) ($payload['error_code'] ?? '');
        if ($errorCode !== '' && $errorCode !== '0000' && $errorCode !== '2000') {
            $message = (string) ($payload['reason'] ?? $payload['error'] ?? "Shop products API failed on page {$page}.");

            throw new RuntimeException($message);
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $itemsPayload
     * @return array<int,string>
     */
    private function productLinksFromPage(array $itemsPayload, string $platform): array
    {
        $items = data_get($itemsPayload, 'item', []);

        if (is_array($items) && array_is_list($items) === false && $items !== []) {
            $items = [$items];
        }

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) use ($platform) {
                $detailUrl = $item['detail_url'] ?? null;

                if (is_string($detailUrl) && filter_var($detailUrl, FILTER_VALIDATE_URL)) {
                    return $detailUrl;
                }

                $numIid = $item['num_iid'] ?? null;

                if (! is_scalar($numIid) || blank((string) $numIid)) {
                    return null;
                }

                return $platform === '1688'
                    ? 'https://detail.1688.com/offer/'.(string) $numIid.'.html'
                    : 'https://item.taobao.com/item.htm?id='.(string) $numIid;
            })
            ->filter(fn ($url) => is_string($url) && (
                $platform === '1688'
                    ? Str::contains($url, '1688.com')
                    : (Str::contains($url, 'taobao.com') || Str::contains($url, 'tmall.com'))
            ))
            ->values()
            ->all();
    }
}
