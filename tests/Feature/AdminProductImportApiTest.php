<?php

namespace Tests\Feature;

use App\Exceptions\TransientFogotApiException;
use App\Jobs\ClassifyImportedProductCategory;
use App\Jobs\ImportMarketplaceProductsFromShops;
use App\Jobs\ImportMarketplaceProductsFromSpreadsheet;
use App\Jobs\ProcessImportedProductDetailImage;
use App\Jobs\ProcessImportedProductMainImage;
use App\Jobs\ProcessImportedProductMedia;
use App\Models\Category;
use App\Models\MarketplaceShopImport;
use App\Models\Product;
use App\Models\User;
use App\Services\BulkMarketplaceProductImportService;
use App\Services\BulkMarketplaceShopImportService;
use App\Services\FogotProductMediaService;
use App\Services\ImportedProductSyncService;
use App\Services\MarketplaceProductFetchService;
use App\Services\MarketplaceShopProductFetchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdminProductImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_spreadsheet_import_with_marketplace_links(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('marketplace-links.csv', implode("\n", [
            'link',
            'https://detail.1688.com/offer/1032188551822.html',
            'https://item.jd.com/100012043978.html',
            'https://example.com/not-supported',
            'https://detail.1688.com/offer/1032188551822.html',
        ]));

        $this->withToken($token)
            ->post('/api/admin/products/import-spreadsheet', [
                'file' => $file,
            ])
            ->assertStatus(202)
            ->assertJsonPath('queued_count', 2)
            ->assertJsonPath('links.0', 'https://detail.1688.com/offer/1032188551822.html')
            ->assertJsonPath('links.1', 'https://item.jd.com/100012043978.html');

        Queue::assertPushed(ImportMarketplaceProductsFromSpreadsheet::class, function (ImportMarketplaceProductsFromSpreadsheet $job) use ($admin) {
            return $job->adminUserId === $admin->id
                && $job->links === [
                    'https://detail.1688.com/offer/1032188551822.html',
                    'https://item.jd.com/100012043978.html',
                ];
        });
    }

    public function test_admin_can_queue_shop_spreadsheet_import_with_taobao_product_seed_urls(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('shop-links.csv', implode("\n", [
            'product_url',
            'https://item.taobao.com/item.htm?ali_refid=abc&id=43556946751&skuId=6070214865171',
            'https://detail.1688.com/offer/935069584004.html?offerId=935069584004&hotSaleSkuId=5989927834046&spm=a260k.home2025.recommendpart.7',
            'https://detail.tmall.com/item.htm?id=668816694920&item_type=ad&skuId=6246256382559',
            'https://example.com/not-supported',
            'https://item.taobao.com/item.htm?id=43556946751&spm=duplicate',
        ]));

        $this->withToken($token)
            ->post('/api/admin/products/import-shop-spreadsheet', [
                'file' => $file,
            ])
            ->assertStatus(202)
            ->assertJsonPath('queued_count', 3)
            ->assertJsonPath('shop_urls.0', 'https://item.taobao.com/item.htm?ali_refid=abc&id=43556946751&skuId=6070214865171')
            ->assertJsonPath('shop_urls.1', 'https://detail.1688.com/offer/935069584004.html?offerId=935069584004&hotSaleSkuId=5989927834046&spm=a260k.home2025.recommendpart.7')
            ->assertJsonPath('shop_urls.2', 'https://detail.tmall.com/item.htm?id=668816694920&item_type=ad&skuId=6246256382559')
            ->assertJsonCount(3, 'import_ids');

        $this->assertDatabaseHas('marketplace_shop_imports', [
            'admin_user_id' => $admin->id,
            'seed_url' => 'https://item.taobao.com/item.htm?ali_refid=abc&id=43556946751&skuId=6070214865171',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ImportMarketplaceProductsFromShops::class, function (ImportMarketplaceProductsFromShops $job) use ($admin) {
            return $job->adminUserId === $admin->id && count($job->shopImportIds) === 3;
        });
    }

    public function test_shop_product_fetch_service_resolves_shop_from_seed_product_and_product_detail_links(): void
    {
        Http::fake([
            'https://api-gw.onebound.cn/taobao/item_get_pro/*' => Http::response([
                'item' => [
                    'num_iid' => '43556946751',
                    'title' => 'Seed product',
                    'price' => '32.50',
                    'detail_url' => 'https://item.taobao.com/item.htm?id=43556946751',
                    'pic_url' => '//img.alicdn.com/imgextra/i2/16134769/seed.jpg',
                    'seller_id' => '16134769',
                    'shop_id' => '34970285',
                ],
                'error_code' => '0000',
            ]),
            'https://api-gw.onebound.cn/taobao/item_search_shop_pro/*' => Http::sequence()
                ->push([
                    'items' => [
                        'shop_id' => '34970285',
                        'page' => '1',
                        'page_count' => 2,
                        'item' => [
                            [
                                'num_iid' => 953121592758,
                                'detail_url' => 'https://item.taobao.com/item.htm?id=953121592758',
                            ],
                            [
                                'num_iid' => 733669788190,
                                'detail_url' => 'https://item.taobao.com/item.htm?id=733669788190',
                            ],
                        ],
                    ],
                    'error_code' => '0000',
                ])
                ->push([
                    'items' => [
                        'shop_id' => '34970285',
                        'page' => '2',
                        'page_count' => 2,
                        'item' => [
                            [
                                'num_iid' => 932300720491,
                                'detail_url' => '',
                            ],
                        ],
                    ],
                    'error_code' => '0000',
                ]),
        ]);

        $service = app(MarketplaceShopProductFetchService::class);
        $shopIdentity = $service->resolveShopFromSeedProductUrl('https://item.taobao.com/item.htm?ali_refid=abc&id=43556946751&skuId=6070214865171');
        $links = $service->fetchProductLinksForShop($shopIdentity['shop_id'], $shopIdentity['seller_id']);

        $this->assertSame('34970285', $shopIdentity['shop_id']);
        $this->assertSame('16134769', $shopIdentity['seller_id']);
        $this->assertSame('43556946751', $shopIdentity['seed_num_iid']);
        $this->assertSame([
            'https://item.taobao.com/item.htm?id=953121592758',
            'https://item.taobao.com/item.htm?id=733669788190',
            'https://item.taobao.com/item.htm?id=932300720491',
        ], $links);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'item_get_pro')
            && $request['num_iid'] === '43556946751');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'seller_info'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'item_search_shop_pro')
            && $request['shop_id'] === '34970285'
            && $request['seller_id'] === '16134769'
            && (int) $request['page'] === 1);
    }

    public function test_shop_product_fetch_service_uses_1688_seller_nick_sid_for_shop_products(): void
    {
        Http::fake([
            'https://api-gw.onebound.cn/1688/item_get/*' => Http::response([
                'item' => [
                    'num_iid' => '570232053443',
                    'title' => 'Seed 1688 product',
                    'price' => '49.00',
                    'detail_url' => 'https://detail.1688.com/offer/570232053443.html',
                    'pic_url' => 'https://cbu01.alicdn.com/img/ibank/seed.jpg',
                    'seller_id' => 3070197752,
                    'shop_id' => 3070197752,
                    'seller_info' => [
                        'sid' => 'b2b-30701977528b4a7',
                        'user_num_id' => 3070197752,
                    ],
                ],
                'error_code' => '0000',
            ]),
            'https://api-gw.onebound.cn/1688/item_search_shop/*' => Http::response([
                'items' => [
                    'page' => '1',
                    'page_count' => 1,
                    'item' => [
                        [
                            'num_iid' => '777193620287',
                            'detail_url' => 'https://detail.1688.com/offer/777193620287.html?',
                        ],
                        [
                            'num_iid' => '778024852442',
                            'detail_url' => '',
                        ],
                    ],
                ],
                'error_code' => '0000',
            ]),
        ]);

        $service = app(MarketplaceShopProductFetchService::class);
        $shopIdentity = $service->resolveShopFromSeedProductUrl('https://detail.1688.com/offer/570232053443.html');
        $links = $service->fetchProductLinksForShop(
            $shopIdentity['shop_id'],
            $shopIdentity['seller_id'],
            $shopIdentity['seed_platform'],
            $shopIdentity['seller_nick'],
        );

        $this->assertSame('1688', $shopIdentity['seed_platform']);
        $this->assertSame('3070197752', $shopIdentity['shop_id']);
        $this->assertSame('3070197752', $shopIdentity['seller_id']);
        $this->assertSame('b2b-30701977528b4a7', $shopIdentity['seller_nick']);
        $this->assertSame([
            'https://detail.1688.com/offer/777193620287.html?',
            'https://detail.1688.com/offer/778024852442.html',
        ], $links);

        Http::assertSent(fn ($request) => str_contains($request->url(), '1688/item_search_shop')
            && $request['seller_nick'] === 'b2b-30701977528b4a7'
            && (int) $request['page'] === 1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'taobao/item_search_shop_pro'));
    }

    public function test_marketplace_product_fetch_service_detects_supported_product_url_ids(): void
    {
        $service = app(MarketplaceProductFetchService::class);

        $numIid = null;
        $platform = $service->detectPlatform('https://detail.1688.com/offer/935069584004.html?offerId=935069584004&hotSaleSkuId=5989927834046', $numIid);
        $this->assertSame('1688', $platform);
        $this->assertSame('935069584004', $numIid);

        $numIid = null;
        $platform = $service->detectPlatform('https://detail.tmall.com/item.htm?id=668816694920&item_type=ad&skuId=6246256382559', $numIid);
        $this->assertSame('taobao', $platform);
        $this->assertSame('668816694920', $numIid);

        $numIid = null;
        $platform = $service->detectPlatform('https://item.jd.com/100012043978.html', $numIid);
        $this->assertSame('jd', $platform);
        $this->assertSame('100012043978', $numIid);
    }

    public function test_shop_bulk_import_discovers_all_links_before_product_processing_starts(): void
    {
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.tmall.com/item.htm?id=668816694920')
            ->andReturn([
                'shop_id' => '34970285',
                'seller_id' => '16134769',
                'seed_platform' => 'taobao',
                'seed_num_iid' => '668816694920',
                'seed_product_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
                'raw' => [],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('34970285', '16134769', 'taobao', null)
            ->andReturn([
                'https://item.taobao.com/item.htm?id=668816694920',
                'https://item.taobao.com/item.htm?id=953121592758',
            ]);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.1688.com/offer/935069584004.html')
            ->andReturn([
                'shop_id' => '247748197',
                'seller_id' => '3244367701',
                'seller_nick' => 'b2b-3244367701',
                'seed_platform' => '1688',
                'seed_num_iid' => '935069584004',
                'seed_product_url' => 'https://detail.1688.com/offer/935069584004.html',
                'raw' => [],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('247748197', '3244367701', '1688', 'b2b-3244367701')
            ->andReturn([
                'https://detail.1688.com/offer/733669788190.html',
            ]);

        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://item.taobao.com/item.htm?id=668816694920',
                'https://item.taobao.com/item.htm?id=953121592758',
                'https://detail.1688.com/offer/935069584004.html',
                'https://detail.1688.com/offer/733669788190.html',
            ]);

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importShopUrlsSequentially([
            'https://detail.tmall.com/item.htm?id=668816694920',
            'https://detail.1688.com/offer/935069584004.html',
        ]);
    }

    public function test_tracked_shop_import_records_seller_shop_ids_links_and_completion_status(): void
    {
        $shopImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
            'status' => 'queued',
        ]);
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $seedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-SHOP-1',
            'name' => 'Tracked shop seed',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-SHOP-2',
            'name' => 'Tracked shop product',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.tmall.com/item.htm?id=668816694920')
            ->andReturn([
                'shop_id' => '34970285',
                'seller_id' => '16134769',
                'seed_platform' => 'taobao',
                'seed_num_iid' => '668816694920',
                'seed_product_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
                'raw' => ['title' => 'Seed item'],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('34970285', '16134769', 'taobao', null)
            ->andReturn([
                'https://item.taobao.com/item.htm?id=953121592758',
            ]);
        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://item.taobao.com/item.htm?id=668816694920',
                'https://item.taobao.com/item.htm?id=953121592758',
            ], Mockery::type('callable'))
            ->andReturnUsing(function (array $links, callable $afterProductProcessed) use ($seedProduct, $shopProduct) {
                $afterProductProcessed($links[0], $seedProduct);
                $afterProductProcessed($links[1], $shopProduct);
            });

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importTrackedShopUrlsSequentially([$shopImport->id]);

        $shopImport->refresh();
        $this->assertSame('completed', $shopImport->status);
        $this->assertSame('taobao', $shopImport->seed_platform);
        $this->assertSame('668816694920', $shopImport->seed_num_iid);
        $this->assertSame('16134769', $shopImport->seller_id);
        $this->assertNull($shopImport->seller_nick);
        $this->assertSame('34970285', $shopImport->shop_id);
        $this->assertSame(2, $shopImport->total_product_links);
        $this->assertSame(2, $shopImport->imported_product_links);
        $this->assertSame(['title' => 'Seed item'], $shopImport->raw_seed_payload);
        $this->assertNotNull($shopImport->started_at);
        $this->assertNotNull($shopImport->completed_at);
    }

    public function test_tracked_1688_shop_import_stores_seller_nick_sid(): void
    {
        $shopImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.1688.com/offer/570232053443.html',
            'status' => 'queued',
        ]);
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $seedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-1688-SHOP-1',
            'name' => 'Tracked 1688 shop seed',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-1688-SHOP-2',
            'name' => 'Tracked 1688 shop product',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.1688.com/offer/570232053443.html')
            ->andReturn([
                'shop_id' => '3070197752',
                'seller_id' => '3070197752',
                'seller_nick' => 'b2b-30701977528b4a7',
                'seed_platform' => '1688',
                'seed_num_iid' => '570232053443',
                'seed_product_url' => 'https://detail.1688.com/offer/570232053443.html',
                'raw' => ['seller_info' => ['sid' => 'b2b-30701977528b4a7']],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('3070197752', '3070197752', '1688', 'b2b-30701977528b4a7')
            ->andReturn([
                'https://detail.1688.com/offer/777193620287.html',
            ]);
        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://detail.1688.com/offer/570232053443.html',
                'https://detail.1688.com/offer/777193620287.html',
            ], Mockery::type('callable'))
            ->andReturnUsing(function (array $links, callable $afterProductProcessed) use ($seedProduct, $shopProduct) {
                $afterProductProcessed($links[0], $seedProduct);
                $afterProductProcessed($links[1], $shopProduct);
            });

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importTrackedShopUrlsSequentially([$shopImport->id]);

        $shopImport->refresh();
        $this->assertSame('completed', $shopImport->status);
        $this->assertSame('1688', $shopImport->seed_platform);
        $this->assertSame('3070197752', $shopImport->seller_id);
        $this->assertSame('b2b-30701977528b4a7', $shopImport->seller_nick);
        $this->assertSame('3070197752', $shopImport->shop_id);
        $this->assertDatabaseHas('marketplace_shop_imports', [
            'id' => $shopImport->id,
            'seller_nick' => 'b2b-30701977528b4a7',
        ]);
    }

    public function test_tracked_shop_import_progress_updates_after_each_processed_product(): void
    {
        $shopImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
            'status' => 'queued',
        ]);
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $firstProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SHOP-PROGRESS-1',
            'name' => 'Shop progress one',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $secondProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SHOP-PROGRESS-2',
            'name' => 'Shop progress two',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->andReturn([
                'shop_id' => '34970285',
                'seller_id' => '16134769',
                'seed_platform' => 'taobao',
                'seed_num_iid' => '668816694920',
                'seed_product_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
                'raw' => [],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('34970285', '16134769', 'taobao', null)
            ->andReturn([
                'https://item.taobao.com/item.htm?id=953121592758',
            ]);
        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://item.taobao.com/item.htm?id=668816694920',
                'https://item.taobao.com/item.htm?id=953121592758',
            ], Mockery::type('callable'))
            ->andReturnUsing(function (array $links, callable $afterProductProcessed) use ($shopImport, $firstProduct, $secondProduct) {
                $afterProductProcessed($links[0], $firstProduct);

                $shopImport->refresh();
                $this->assertSame('importing_products', $shopImport->status);
                $this->assertSame(1, $shopImport->imported_product_links);
                $this->assertSame(2, $shopImport->total_product_links);
                $this->assertSame($firstProduct->id, $shopImport->metadata['last_processed_product_id']);

                $afterProductProcessed($links[1], $secondProduct);
            });

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importTrackedShopUrlsSequentially([$shopImport->id]);

        $shopImport->refresh();
        $this->assertSame('completed', $shopImport->status);
        $this->assertSame(2, $shopImport->imported_product_links);
        $this->assertSame($secondProduct->id, $shopImport->metadata['last_processed_product_id']);
    }

    public function test_tracked_shop_import_does_not_force_progress_to_total_when_one_product_fails(): void
    {
        $shopImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
            'status' => 'queued',
        ]);
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $processedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SHOP-PARTIAL-1',
            'name' => 'Shop partial one',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->andReturn([
                'shop_id' => '34970285',
                'seller_id' => '16134769',
                'seed_platform' => 'taobao',
                'seed_num_iid' => '668816694920',
                'seed_product_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
                'raw' => [],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('34970285', '16134769', 'taobao', null)
            ->andReturn([
                'https://item.taobao.com/item.htm?id=953121592758',
            ]);
        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://item.taobao.com/item.htm?id=668816694920',
                'https://item.taobao.com/item.htm?id=953121592758',
            ], Mockery::type('callable'))
            ->andReturnUsing(function (array $links, callable $afterProductProcessed) use ($processedProduct) {
                $afterProductProcessed($links[0], $processedProduct);
            });

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importTrackedShopUrlsSequentially([$shopImport->id]);

        $shopImport->refresh();
        $this->assertSame('failed', $shopImport->status);
        $this->assertSame(1, $shopImport->imported_product_links);
        $this->assertSame(2, $shopImport->total_product_links);
        $this->assertSame('Only 1 of 2 product links were processed.', $shopImport->error);
    }

    public function test_tracked_shop_import_continues_when_one_seed_fails(): void
    {
        $failedImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.tmall.com/item.htm?id=111',
            'status' => 'queued',
        ]);
        $successfulImport = MarketplaceShopImport::query()->create([
            'seed_url' => 'https://detail.tmall.com/item.htm?id=222',
            'status' => 'queued',
        ]);
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $seedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-SHOP-CONTINUE-1',
            'name' => 'Tracked shop continue seed',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRACKED-SHOP-CONTINUE-2',
            'name' => 'Tracked shop continue product',
            'description' => 'Processed.',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
            'import_status' => 'completed',
        ]);
        $shopProductFetchService = Mockery::mock(MarketplaceShopProductFetchService::class);
        $productImportService = Mockery::mock(BulkMarketplaceProductImportService::class);

        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.tmall.com/item.htm?id=111')
            ->andThrow(new \RuntimeException('Seed item did not include seller_id.'));
        $shopProductFetchService
            ->shouldReceive('resolveShopFromSeedProductUrl')
            ->once()
            ->with('https://detail.tmall.com/item.htm?id=222')
            ->andReturn([
                'shop_id' => 'shop-222',
                'seller_id' => 'seller-222',
                'seed_platform' => 'taobao',
                'seed_num_iid' => '222',
                'seed_product_url' => 'https://detail.tmall.com/item.htm?id=222',
                'raw' => ['title' => 'Working seed'],
            ]);
        $shopProductFetchService
            ->shouldReceive('fetchProductLinksForShop')
            ->once()
            ->with('shop-222', 'seller-222', 'taobao', null)
            ->andReturn([
                'https://item.taobao.com/item.htm?id=333',
            ]);
        $productImportService
            ->shouldReceive('importLinksSequentially')
            ->once()
            ->with([
                'https://item.taobao.com/item.htm?id=222',
                'https://item.taobao.com/item.htm?id=333',
            ], Mockery::type('callable'))
            ->andReturnUsing(function (array $links, callable $afterProductProcessed) use ($seedProduct, $shopProduct) {
                $afterProductProcessed($links[0], $seedProduct);
                $afterProductProcessed($links[1], $shopProduct);
            });

        $service = new BulkMarketplaceShopImportService($shopProductFetchService, $productImportService);
        $service->importTrackedShopUrlsSequentially([$failedImport->id, $successfulImport->id]);

        $failedImport->refresh();
        $successfulImport->refresh();

        $this->assertSame('failed', $failedImport->status);
        $this->assertSame('Seed item did not include seller_id.', $failedImport->error);
        $this->assertSame('resolving_seed', $failedImport->metadata['failed_stage']);
        $this->assertSame('completed', $successfulImport->status);
        $this->assertSame('seller-222', $successfulImport->seller_id);
        $this->assertSame('shop-222', $successfulImport->shop_id);
        $this->assertSame(2, $successfulImport->total_product_links);
    }

    public function test_fogot_image_processing_retries_transient_statuses_before_failing(): void
    {
        Config::set('services.fogot.retry_times', 2);
        Config::set('services.fogot.retry_sleep_ms', 0);
        Config::set('services.fogot.retry_statuses', [420, 500]);
        Config::set('services.fogot.timeout', 900);

        Http::fake([
            'https://py.fogot.cn/api/product/detail/image/translate' => Http::sequence()
                ->push(['message' => 'still processing'], 420)
                ->push(['message' => 'temporary server error'], 500)
                ->push([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode('translated-image'),
                        ],
                    ],
                ], 200),
        ]);

        $images = app(FogotProductMediaService::class)
            ->translateImage('https://cbu01.alicdn.com/img/ibank/test.jpg');

        $this->assertCount(1, $images);
        $this->assertSame('image/jpeg', $images[0]['mime_type']);
        $this->assertSame(base64_encode('translated-image'), $images[0]['data']);
    }

    public function test_queued_image_job_retries_transient_fogot_failures_before_writing_import_error(): void
    {
        Config::set('services.fogot.retry_times', 0);
        Config::set('services.fogot.retry_sleep_ms', 0);
        Config::set('services.fogot.retry_statuses', [500]);

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => Http::response(['message' => 'temporary server error'], 500),
        ]);

        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TRANSIENT-RETRY-1',
            'name' => 'Transient retry product',
            'description' => 'Transient retry description',
            'source_payload' => [],
            'import_status' => 'processing',
            'import_error' => null,
            'import_total_tasks' => 1,
            'import_completed_tasks' => 0,
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);

        $job = new ProcessImportedProductDetailImage(
            $product->id,
            'https://img.alicdn.com/imgextra/i3/1071802411/test.jpg',
            'gallery',
            1,
            'redraw',
        );

        try {
            $job->handle(app(ImportedProductSyncService::class));
            $this->fail('Transient Fogot failures should be rethrown so the queue can retry the image job.');
        } catch (TransientFogotApiException $exception) {
            $product->refresh();
            $this->assertSame(0, $product->import_completed_tasks);
            $this->assertNull($product->import_error);

            $job->failed($exception);
        }

        $product->refresh();
        $this->assertSame(1, $product->import_completed_tasks);
        $this->assertStringContainsString('Gallery image processing failed', $product->import_error);
        $this->assertStringContainsString('after 1 attempt(s)', $product->import_error);
    }

    public function test_import_progress_counts_each_media_task_only_once(): void
    {
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'DUPLICATE-PROGRESS-1',
            'name' => 'Duplicate progress product',
            'description' => 'Duplicate progress description',
            'source_payload' => [],
            'import_api_debug' => ['completed_task_keys' => []],
            'import_status' => 'processing',
            'import_error' => null,
            'import_total_tasks' => 1,
            'import_completed_tasks' => 0,
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);
        $service = app(ImportedProductSyncService::class);
        $imageUrl = 'https://img.alicdn.com/imgextra/i1/duplicate-task.jpg';

        $service->recordDetailImageFailure($product, $imageUrl, 'gallery', 1, 'redraw', new \RuntimeException('temporary failure'));
        $service->recordDetailImageFailure($product->fresh(), $imageUrl, 'gallery', 1, 'redraw', new \RuntimeException('temporary failure replay'));

        $product->refresh();
        $this->assertSame(1, $product->import_completed_tasks);
        $this->assertSame(1, $product->import_total_tasks);
        $this->assertSame('failed', $product->import_status);
        $this->assertCount(1, $product->import_api_debug['completed_task_keys']);
    }

    public function test_product_media_job_processes_one_product_serially_without_dispatching_image_jobs(): void
    {
        Queue::fake();

        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SERIAL-MEDIA-1',
            'name' => 'Serial media product',
            'description' => 'Serial media description',
            'source_payload' => [
                'platform' => 'taobao',
                'num_iid' => '123456789',
                'title' => 'Serial media product',
                'images' => [
                    'https://img.alicdn.com/imgextra/i1/test-gallery.jpg',
                ],
                'description_images' => [
                    'https://img.alicdn.com/imgextra/i1/test-description.jpg',
                ],
            ],
            'import_status' => 'pending',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);

        $syncService = Mockery::mock(ImportedProductSyncService::class);
        $syncService
            ->shouldReceive('processSequentially')
            ->once()
            ->with(Mockery::on(fn (Product $handledProduct) => $handledProduct->is($product)), $product->source_payload);

        (new ProcessImportedProductMedia($product->id))->handle($syncService);

        Queue::assertNotPushed(ProcessImportedProductDetailImage::class);
        Queue::assertNotPushed(ProcessImportedProductMainImage::class);
        Queue::assertNotPushed(ClassifyImportedProductCategory::class);
    }

    public function test_description_classifier_failure_continues_to_translate_image(): void
    {
        Config::set('services.fogot.retry_times', 0);
        Config::set('services.fogot.retry_sleep_ms', 0);
        Config::set('services.fogot.retry_statuses', [500]);

        Http::fake([
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response(['detail' => 'server error'], 500),
            'https://py.fogot.cn/api/product/detail/image/translate' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('translated-description'),
                    ],
                ],
            ]),
        ]);

        Storage::fake('public');

        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'CLASSIFIER-FAIL-OPEN-1',
            'name' => 'Classifier fail open product',
            'description' => 'Classifier fail open description',
            'source_payload' => [],
            'import_status' => 'processing',
            'import_error' => null,
            'import_total_tasks' => 2,
            'import_completed_tasks' => 0,
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);

        app(ImportedProductSyncService::class)->processDetailImage(
            $product,
            'https://img.alicdn.com/imgextra/i2/3891274461/test.jpg',
            'description',
            0,
            'translate',
            true,
        );

        $product->refresh();
        $this->assertSame(2, $product->import_completed_tasks);
        $this->assertStringContainsString('Description image classification failed', $product->import_error);
        $this->assertSame('classifier_unavailable', data_get($product->import_api_debug, 'classify_description_results.0.status'));
        $this->assertSame('processed', data_get($product->import_api_debug, 'translate_description_results.0.status'));
    }

    public function test_serial_product_processing_counts_each_api_step_before_completion(): void
    {
        Config::set('services.fogot.retry_times', 0);
        Config::set('services.fogot.retry_sleep_ms', 0);
        Storage::fake('public');

        Http::fake([
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys',
                        'L1_ZH' => '玩具',
                    ],
                ],
            ]),
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => 'ä»‹ç»å•†å“',
            ]),
            'https://py.fogot.cn/api/product/detail/image/translate' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('translated-image'),
                    ],
                ],
            ]),
            'https://py.fogot.cn/api/product/image/redraw' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/png',
                        'data' => base64_encode('redrawn-image'),
                    ],
                ],
            ]),
        ]);

        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'FULL-API-PROGRESS-1',
            'name' => 'Full API progress product',
            'description' => 'Full API progress description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);
        $source = [
            'platform' => 'taobao',
            'num_iid' => '123456789',
            'title' => 'Full API progress product',
            'description' => 'Full API progress description',
            'detail_url' => 'https://item.taobao.com/item.htm?id=123456789',
            'image_url' => 'https://img.alicdn.com/main.jpg',
            'main_image_url' => 'https://img.alicdn.com/main.jpg',
            'images' => [
                'https://img.alicdn.com/main.jpg',
                'https://img.alicdn.com/gallery.jpg',
            ],
            'description_images' => [
                'https://img.alicdn.com/description.jpg',
            ],
            'variants' => [
                [
                    'sku_id' => 'sku-1',
                    'label' => 'Blue',
                    'option_values' => [
                        ['key' => '0:1', 'group_name' => 'Color', 'value' => 'Blue'],
                    ],
                    'image_url' => 'https://img.alicdn.com/variant.jpg',
                    'price' => 1,
                    'stock_quantity' => 10,
                ],
            ],
        ];

        app(ImportedProductSyncService::class)->processSequentially($product, $source);

        $product->refresh();
        $this->assertSame(6, $product->import_total_tasks);
        $this->assertSame(6, $product->import_completed_tasks);
        $this->assertSame('completed', $product->import_status);
        $this->assertCount(6, $product->import_api_debug['completed_task_keys']);
        $this->assertSame('approved', data_get($product->import_api_debug, 'classify_description_results.0.status'));
        $this->assertSame('processed', data_get($product->import_api_debug, 'translate_description_results.0.status'));
        $this->assertSame('processed', data_get($product->import_api_debug, 'translate_variant_results.0.status'));
        $this->assertSame('processed', data_get($product->import_api_debug, 'redraw_gallery_results.0.status'));
        Http::assertSentCount(6);
    }

    public function test_imported_product_uses_source_images_until_media_fully_completes(): void
    {
        $category = Category::query()->create([
            'name' => 'Imported Products',
            'slug' => 'imported-products',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SRC-IMAGES-1',
            'name' => 'Source image fallback product',
            'description' => 'Source image fallback description',
            'image_url' => 'products/1/redraw-gallery/01-processed.jpg',
            'source_image_url' => 'https://cbu01.alicdn.com/img/ibank/main.jpg',
            'source_payload' => [
                'main_image_url' => 'https://cbu01.alicdn.com/img/ibank/main.jpg',
                'image_url' => 'https://cbu01.alicdn.com/img/ibank/main.jpg',
                'images' => [
                    'https://cbu01.alicdn.com/img/ibank/gallery-1.jpg',
                    'https://cbu01.alicdn.com/img/ibank/gallery-2.jpg',
                ],
                'description_images' => [],
                'description_html' => '<p><img src="https://cbu01.alicdn.com/img/ibank/desc-from-html.jpg" /></p>',
            ],
            'import_status' => 'processing',
            'import_error' => null,
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 10,
            'base_price' => 1,
        ]);

        $product->productImages()->create([
            'path' => 'products/1/redraw-gallery/01-processed.jpg',
            'source_url' => 'https://cbu01.alicdn.com/img/ibank/main.jpg',
            'section' => 'gallery',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $product->load('productImages', 'variants');

        $this->assertSame([
            'https://cbu01.alicdn.com/img/ibank/main.jpg',
            'https://cbu01.alicdn.com/img/ibank/gallery-1.jpg',
            'https://cbu01.alicdn.com/img/ibank/gallery-2.jpg',
        ], $product->galleryPaths()->all());
        $this->assertSame([
            'https://cbu01.alicdn.com/img/ibank/desc-from-html.jpg',
        ], $product->descriptionImagePaths()->all());

        $product->forceFill([
            'import_status' => 'completed',
            'import_error' => 'One image failed.',
        ])->save();

        $this->assertSame('https://cbu01.alicdn.com/img/ibank/main.jpg', $product->refresh()->load('productImages', 'variants')->galleryPaths()->first());

        $product->forceFill([
            'import_error' => null,
        ])->save();

        $this->assertSame([
            'products/1/redraw-gallery/01-processed.jpg',
        ], $product->refresh()->load('productImages', 'variants')->galleryPaths()->all());
    }

    public function test_admin_can_fetch_full_marketplace_product_details_with_gallery_images(): void
    {
        Http::fake([
            'https://api-gw.onebound.cn/*' => Http::response([
                'item' => [
                    'num_iid' => '1032188551822',
                    'title' => 'Imported Plush Toy',
                    'price' => '5.50',
                    'detail_url' => 'https://detail.1688.com/offer/1032188551822.html',
                    'pic_url' => 'https://cbu01.alicdn.com/img/ibank/main.jpg',
                    'item_imgs' => [
                        ['url' => 'https://cbu01.alicdn.com/img/ibank/gallery-1.jpg'],
                    ],
                    'props_img' => [
                        '0:0' => 'https://cbu01.alicdn.com/img/ibank/sku-1.jpg',
                    ],
                    'skus' => [
                        'sku' => [
                            [
                                'sku_id' => 'sku-1',
                                'properties' => '0:0',
                                'properties_name' => '0:0:Color:Red',
                                'price' => 5.50,
                                'quantity' => 10,
                            ],
                        ],
                    ],
                    'desc' => '{&quot;styleType&quot;:&quot;offer-type-1&quot;,&quot;items&quot;:&quot;1033797201933&quot;,&quot;usemap&quot;:&quot;_sdmap_0&quot;}<div><img src="https://cbu01.alicdn.com/img/ibank/desc-1.jpg" /><img src="https://www.o0b.cn/i.php?t.png&rid=gw-3.69fe32d298ef4&p=1736835377&k=t7100&t=1778266837" /></div>',
                    'props' => [
                        ['name' => 'Material', 'value' => 'Plush'],
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => '介绍商品',
            ], 200),
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys & Games',
                        'L1_ZH' => '玩具',
                        'L2_EN' => 'Educational / STEM Toys',
                        'L2_ZH' => '益智/科教玩具',
                        'L3_EN' => 'Science Kits',
                        'L3_ZH' => '毛绒玩具',
                        'number' => 0,
                        'item_name' => 'Imported Plush Toy',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/products/from-link?link=https://detail.1688.com/offer/1032188551822.html')
            ->assertOk()
            ->assertJsonPath('product.title', 'Imported Plush Toy')
            ->assertJsonPath('product.detail_url', 'https://detail.1688.com/offer/1032188551822.html')
            ->assertJsonPath('product.main_image_url', 'https://cbu01.alicdn.com/img/ibank/main.jpg')
            ->assertJsonPath('product.classified_category', null)
            ->assertJsonCount(1, 'product.images')
            ->assertJsonCount(0, 'product.processed_gallery_images')
            ->assertJsonCount(1, 'product.description_images')
            ->assertJsonCount(0, 'product.processed_description_images')
            ->assertJsonPath('product.images.0', 'https://cbu01.alicdn.com/img/ibank/gallery-1.jpg')
            ->assertJsonMissingPath('product.images.1')
            ->assertJsonPath('product.variants.0.image_url', 'https://cbu01.alicdn.com/img/ibank/sku-1.jpg')
            ->assertJsonPath('product.description_images.0', 'https://cbu01.alicdn.com/img/ibank/desc-1.jpg')
            ->assertJsonMissingPath('product.description_images.1')
            ->assertJsonPath('product.description', 'Material: Plush')
            ->assertJsonPath('product.processed_main_image', null);
    }

    public function test_admin_can_create_imported_product_and_store_processed_images_locally(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        $redrawCalls = 0;
        $translateCalls = 0;
        $descriptionClassifyCalls = 0;
        $productCategoryCalls = 0;

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => function () use (&$redrawCalls) {
                $redrawCalls++;

                return Http::response([
                    'body' => [
                        'images' => [
                            [
                                'mime_type' => 'image/jpeg',
                                'data' => base64_encode('processed-main-image'),
                            ],
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/translate' => function () use (&$translateCalls) {
                $translateCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode("processed-image-{$translateCalls}"),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/classify' => function () use (&$descriptionClassifyCalls) {
                $descriptionClassifyCalls++;

                return Http::response([
                    'category' => '介绍商品',
                ], 200);
            },
            'https://py.fogot.cn/api/product/category/classify' => function () use (&$productCategoryCalls) {
                $productCategoryCalls++;

                return Http::response([
                    'items' => [
                        [
                            'L1_EN' => 'Toys & Games',
                            'L1_ZH' => '玩具',
                            'L2_EN' => 'Educational / STEM Toys',
                            'L2_ZH' => '益智/科教玩具',
                            'L3_EN' => 'Science Kits',
                            'L3_ZH' => 'Imported Toys',
                            'number' => 0,
                            'item_name' => 'Imported Plush Toy',
                        ],
                    ],
                ], 200);
            },
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Imported Toys',
            'slug' => 'imported-toys',
            'sort_order' => 1,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'IM-1032188551822',
            'name' => 'Imported Plush Toy',
            'description' => 'Imported description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 100,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'base_price' => 5.50,
            'price_tiers' => [
                ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 5.50],
                ['min_quantity' => 10, 'max_quantity' => null, 'price' => 4.75],
            ],
            'import_source' => [
                'platform' => '1688',
                'num_iid' => '1032188551822',
                'detail_url' => 'https://detail.1688.com/offer/1032188551822.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'classified_category' => 'Imported Toys',
                'description' => 'Imported description',
                'description_html' => '<p>Imported description</p>',
                'images' => [
                    'https://cdn.example.com/gallery-1.jpg',
                ],
                'description_images' => [
                    'https://cdn.example.com/desc-1.jpg',
                ],
                'variants' => [
                    [
                        'sku_id' => 'sku-1',
                        'properties_key' => '1627207:29995729228',
                        'properties_name' => '1627207:29995729228:颜色分类:香芋紫-15升',
                        'label' => '香芋紫-15升',
                        'image_url' => 'https://cdn.example.com/gallery-1.jpg',
                        'price' => 5.50,
                        'original_price' => 5.50,
                        'stock_quantity' => 100,
                        'option_values' => [
                            [
                                'key' => '1627207:29995729228',
                                'group_name' => '颜色分类',
                                'value' => '香芋紫-15升',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $product = Product::query()->with('productImages')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonCount(1, 'images')
            ->assertJsonCount(1, 'description_images');

        $this->assertSame('1688', $product->source_platform);
        $this->assertSame('1032188551822', $product->source_product_id);
        $this->assertSame('Imported Toys', $product->source_category_label);
        $this->assertSame([
            'L1_EN' => 'Toys & Games',
            'L1_ZH' => '玩具',
            'L2_EN' => 'Educational / STEM Toys',
            'L2_ZH' => '益智/科教玩具',
            'L3_EN' => 'Science Kits',
            'L3_ZH' => 'Imported Toys',
            'number' => 0,
            'item_name' => 'Imported Plush Toy',
        ], $product->cat_from_api);
        $this->assertSame('completed', $product->import_status);
        $this->assertCount(4, $product->productImages);
        $this->assertCount(2, $product->productImages->where('section', 'gallery'));
        $this->assertCount(1, $product->productImages->where('section', 'description'));
        $this->assertCount(1, $product->productImages->where('section', 'variant'));
        $this->assertCount(1, $product->variants);
        $this->assertSame('香芋紫-15升', $product->variants->first()->label);
        $this->assertSame(2, $redrawCalls);
        $this->assertSame(2, $translateCalls);
        $this->assertSame(1, $descriptionClassifyCalls);
        $this->assertSame(1, $productCategoryCalls);
        $this->assertTrue(str_starts_with((string) $product->variants->first()?->image_url, 'products/1/variant-images/'));

        foreach ($product->productImages as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_add_product_import_source_creates_only_single_product_and_never_fetches_shop_items(): void
    {
        Http::fake([
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys & Games',
                        'L2_EN' => 'Educational / STEM Toys',
                        'number' => 0,
                        'item_name' => 'Single Taobao Product',
                    ],
                ],
            ], 200),
            'https://api-gw.onebound.cn/taobao/item_search_shop_pro/*' => Http::response([
                'items' => [
                    'item' => [
                        ['detail_url' => 'https://item.taobao.com/item.htm?id=999'],
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Imported Toys',
            'slug' => 'imported-toys',
            'sort_order' => 1,
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/admin/products', [
                'category_id' => $category->id,
                'sku' => 'TB-668816694920',
                'name' => 'Single Taobao Product',
                'description' => 'Created from Add Product modal.',
                'moq' => 1,
                'lead_time_min_days' => 3,
                'lead_time_max_days' => 5,
                'stock_quantity' => 100,
                'is_verified' => true,
                'is_customizable' => false,
                'is_active' => true,
                'base_price' => 298,
                'price_tiers' => [
                    ['min_quantity' => 1, 'max_quantity' => null, 'price' => 298],
                ],
                'import_source' => [
                    'import_mode' => 'single_product',
                    'source' => 'admin_add_product_modal',
                    'platform' => 'taobao',
                    'num_iid' => '668816694920',
                    'detail_url' => 'https://detail.tmall.com/item.htm?id=668816694920',
                    'description' => 'Created from Add Product modal.',
                    'images' => [],
                    'description_images' => [],
                    'variants' => [],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('sku', 'TB-668816694920');

        $this->assertSame(1, Product::query()->count());
        $this->assertDatabaseCount('marketplace_shop_imports', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'item_search_shop_pro'));
    }

    public function test_import_processing_extracts_description_images_from_description_html(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        $descriptionClassifyCalls = 0;
        $translateCalls = 0;

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('processed-main-image'),
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/classify' => function () use (&$descriptionClassifyCalls) {
                $descriptionClassifyCalls++;

                return Http::response([
                    'category' => 'ä»‹ç»å•†å“',
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/translate' => function () use (&$translateCalls) {
                $translateCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode('processed-description-image'),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys & Games',
                        'L1_ZH' => 'çŽ©å…·',
                        'L2_EN' => 'Educational / STEM Toys',
                        'L2_ZH' => 'ç›Šæ™º/ç§‘æ•™çŽ©å…·',
                        'L3_EN' => 'Science Kits',
                        'L3_ZH' => 'Imported Toys',
                        'number' => 0,
                        'item_name' => 'Imported Html Product',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Imported HTML',
            'slug' => 'imported-html',
            'sort_order' => 1,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'HTML-DESC-1',
            'name' => 'Imported Html Product',
            'description' => 'Imported description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 100,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'base_price' => 5.50,
            'price_tiers' => [
                ['min_quantity' => 1, 'max_quantity' => null, 'price' => 5.50],
            ],
            'import_source' => [
                'platform' => '1688',
                'num_iid' => 'html-desc-1',
                'detail_url' => 'https://detail.1688.com/offer/html-desc-1.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'description' => 'Imported description',
                'description_html' => '<div><img src="https://cbu01.alicdn.com/img/ibank/desc-html-1.jpg" /><img src="https://www.o0b.cn/i.php?t.png&rid=gw-test" /></div>',
                'images' => [],
                'description_images' => [],
                'variants' => [],
            ],
        ])->assertCreated()
            ->assertJsonCount(1, 'description_images');

        $product = Product::query()->with('productImages')->firstOrFail();

        $this->assertSame([
            'https://cbu01.alicdn.com/img/ibank/desc-html-1.jpg',
        ], data_get($product->source_payload, 'original_description_images'));
        $this->assertCount(1, $product->productImages->where('section', 'description'));
        $this->assertSame(1, $descriptionClassifyCalls);
        $this->assertSame(1, $translateCalls);
    }

    public function test_description_images_are_skipped_when_classify_api_marks_them_as_non_product_content(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        $translateCalls = 0;

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('processed-main-image'),
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/translate' => function () use (&$translateCalls) {
                $translateCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode("processed-image-{$translateCalls}"),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => '其他',
            ], 200),
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys & Games',
                        'L1_ZH' => '玩具',
                        'L2_EN' => 'Educational / STEM Toys',
                        'L2_ZH' => '益智/科教玩具',
                        'L3_EN' => 'Science Kits',
                        'L3_ZH' => 'Imported Toys',
                        'number' => 0,
                        'item_name' => 'Imported Plush Toy',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Imported Toys',
            'slug' => 'imported-toys',
            'sort_order' => 1,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'IM-1032188551822',
            'name' => 'Imported Plush Toy',
            'description' => 'Imported description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 100,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'base_price' => 5.50,
            'price_tiers' => [
                ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 5.50],
            ],
            'import_source' => [
                'platform' => '1688',
                'num_iid' => '1032188551822',
                'detail_url' => 'https://detail.1688.com/offer/1032188551822.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'classified_category' => 'Imported Toys',
                'description' => 'Imported description',
                'description_html' => '<p>Imported description</p>',
                'images' => [
                    'https://cdn.example.com/gallery-1.jpg',
                ],
                'description_images' => [
                    'https://cdn.example.com/desc-1.jpg',
                ],
                'variants' => [],
            ],
        ])->assertCreated();

        $product = Product::query()->with('productImages')->firstOrFail();

        $this->assertCount(2, $product->productImages);
        $this->assertCount(0, $product->productImages->where('section', 'description'));
        $this->assertCount(2, $product->galleryPaths()->all());
        $this->assertTrue(collect($product->galleryPaths())->every(fn (string $path) => str_starts_with($path, 'products/1/redraw-gallery/')));
        $this->assertSame([], $product->descriptionImagePaths()->all());
        $this->assertSame(0, $translateCalls);
    }

    public function test_unique_variant_property_images_are_translated_when_not_already_present_in_item_images(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        $redrawCalls = 0;
        $translateCalls = 0;

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => function () use (&$redrawCalls) {
                $redrawCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode("processed-redraw-{$redrawCalls}"),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/translate' => function () use (&$translateCalls) {
                $translateCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode("processed-translate-{$translateCalls}"),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => '介绍商品',
            ], 200),
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Sports',
                        'L1_ZH' => '运动',
                        'L2_EN' => 'Training',
                        'L2_ZH' => '训练用品',
                        'L3_EN' => 'Hurdles',
                        'L3_ZH' => '跨栏',
                        'number' => 0,
                        'item_name' => 'Training Hurdle',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Training Gear',
            'slug' => 'training-gear',
            'sort_order' => 1,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'IM-TRAINING-1',
            'name' => 'Training Hurdle',
            'description' => 'Imported description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 100,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'base_price' => 5.50,
            'price_tiers' => [
                ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 5.50],
            ],
            'import_source' => [
                'platform' => '1688',
                'num_iid' => '1032147999662',
                'detail_url' => 'https://detail.1688.com/offer/1032147999662.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'classified_category' => 'Training Gear',
                'description' => 'Imported description',
                'description_html' => '<p>Imported description</p>',
                'images' => [
                    'https://cdn.example.com/gallery-1.jpg',
                ],
                'description_images' => [],
                'variants' => [
                    [
                        'sku_id' => 'sku-1',
                        'properties_key' => '0:0',
                        'properties_name' => '0:0:颜色:15cm',
                        'label' => '15cm',
                        'image_url' => 'https://cdn.example.com/variant-1.jpg',
                        'price' => 5.50,
                        'original_price' => 5.50,
                        'stock_quantity' => 100,
                        'option_values' => [
                            [
                                'key' => '0:0',
                                'group_name' => '颜色',
                                'value' => '15cm',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertCreated();

        $product = Product::query()->with('productImages', 'variants')->firstOrFail();

        $this->assertSame(2, $redrawCalls);
        $this->assertSame(1, $translateCalls);
        $this->assertCount(3, $product->productImages);
        $this->assertNotNull($product->variants->first()?->image_url);
        $this->assertTrue(str_starts_with((string) $product->variants->first()?->image_url, 'products/1/variant-images/'));
    }

    public function test_only_first_four_gallery_images_plus_main_use_redraw_api(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        $redrawCalls = 0;

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => function () use (&$redrawCalls) {
                $redrawCalls++;

                return Http::response([
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode("processed-redraw-{$redrawCalls}"),
                        ],
                    ],
                ], 200);
            },
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Toys & Games',
                        'L1_ZH' => '玩具',
                        'L2_EN' => 'Educational / STEM Toys',
                        'L2_ZH' => '益智/科教玩具',
                        'L3_EN' => 'Science Kits',
                        'L3_ZH' => '科学实验套装',
                        'number' => 0,
                        'item_name' => 'Training Hurdle',
                    ],
                ],
            ], 200),
            'https://cdn.example.com/*' => Http::response('raw-image', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Training Gear',
            'slug' => 'training-gear',
            'sort_order' => 1,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $galleryImages = [
            'https://cdn.example.com/gallery-1.jpg',
            'https://cdn.example.com/gallery-2.jpg',
            'https://cdn.example.com/gallery-3.jpg',
            'https://cdn.example.com/gallery-4.jpg',
            'https://cdn.example.com/gallery-5.jpg',
            'https://cdn.example.com/gallery-6.jpg',
        ];

        $this->withToken($token)->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'IM-REDRAW-4',
            'name' => 'Training Hurdle',
            'description' => 'Imported description',
            'moq' => 1,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 5,
            'stock_quantity' => 100,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'base_price' => 5.50,
            'price_tiers' => [
                ['min_quantity' => 1, 'max_quantity' => 9, 'price' => 5.50],
            ],
            'import_source' => [
                'platform' => '1688',
                'num_iid' => '609733994005',
                'detail_url' => 'https://detail.1688.com/offer/609733994005.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'classified_category' => null,
                'description' => 'Imported description',
                'description_html' => '<p>Imported description</p>',
                'images' => $galleryImages,
                'description_images' => [],
                'variants' => [],
            ],
        ])->assertCreated();

        $product = Product::query()->with('productImages')->firstOrFail();

        $this->assertSame(5, $redrawCalls);
        $this->assertCount(5, $product->productImages);
        $this->assertNotContains('https://cdn.example.com/gallery-5.jpg', $product->productImages->pluck('source_url')->all());
        $this->assertNotContains('https://cdn.example.com/gallery-6.jpg', $product->productImages->pluck('source_url')->all());

        foreach ($product->productImages as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_tracking_pixel_images_are_hidden_from_existing_product_description_images(): void
    {
        $category = Category::query()->create([
            'name' => 'Pixel Tests',
            'slug' => 'pixel-tests',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PX-1',
            'name' => 'Pixel Filter Product',
            'description' => 'Test',
            'image_url' => 'products/1/redraw-gallery/main.jpg',
            'base_price' => 1,
            'moq' => 1,
            'lead_time_min_days' => 1,
            'lead_time_max_days' => 2,
            'stock_quantity' => 1,
            'is_verified' => true,
            'is_customizable' => false,
            'is_active' => true,
            'source_payload' => [
                'description_images' => [
                    'https://cbu01.alicdn.com/img/ibank/desc-1.jpg',
                    'https://www.o0b.cn/i.php?t.png&rid=gw-3.69fe32d298ef4&p=1736835377&k=t7100&t=1778266837',
                ],
            ],
        ]);

        $product->productImages()->createMany([
            [
                'path' => 'products/1/description-images/01-real.jpg',
                'source_url' => 'https://cbu01.alicdn.com/img/ibank/desc-1.jpg',
                'section' => 'description',
                'sort_order' => 0,
                'is_primary' => false,
            ],
            [
                'path' => 'products/1/description-images/02-pixel.jpg',
                'source_url' => 'https://www.o0b.cn/i.php?t.png&rid=gw-4.69fe3bc7891c4&p=767692421&k=t7100&t=1778269134',
                'section' => 'description',
                'sort_order' => 1,
                'is_primary' => false,
            ],
        ]);

        $product->load('productImages');

        $this->assertSame([
            'products/1/description-images/01-real.jpg',
        ], $product->descriptionImagePaths()->all());
    }

    public function test_completed_product_detail_uses_stored_gallery_and_source_description_fallback(): void
    {
        $category = Category::query()->create([
            'name' => 'Processed Media',
            'slug' => 'processed-media',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PROC-98',
            'name' => 'Processed Product',
            'description' => 'Test product',
            'image_url' => 'products/98/redraw-gallery/01-main.jpg',
            'source_image_url' => 'https://cdn.example.com/main-source.jpg',
            'import_status' => 'completed',
            'moq' => 1,
            'lead_time_min_days' => 1,
            'lead_time_max_days' => 2,
            'stock_quantity' => 10,
            'base_price' => 9.99,
            'source_payload' => [
                'main_image_url' => 'https://cdn.example.com/main-source.jpg',
                'images' => [
                    'https://cdn.example.com/gallery-1.jpg',
                    'https://cdn.example.com/gallery-2.jpg',
                    'https://cdn.example.com/gallery-3.jpg',
                    'https://cdn.example.com/gallery-4.jpg',
                ],
                'description_images' => [
                    'https://cdn.example.com/desc-1.jpg',
                ],
            ],
        ]);

        $product->productImages()->create([
            'path' => 'products/98/redraw-gallery/01-main.jpg',
            'source_url' => 'https://cdn.example.com/main-source.jpg',
            'section' => 'gallery',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'images')
            ->assertJsonCount(1, 'description_images')
            ->assertJsonPath('description_images.0', 'https://cdn.example.com/desc-1.jpg');
    }

    public function test_admin_can_retry_imported_product_processing(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('processed-main-image'),
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/translate' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('processed-variant-image'),
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => '介绍商品',
            ], 200),
            'https://py.fogot.cn/api/product/category/classify' => Http::response([
                'items' => [
                    [
                        'L1_EN' => 'Early Years',
                        'L1_ZH' => '幼儿启蒙',
                        'L2_EN' => 'Learning Areas',
                        'L2_ZH' => '学习领域',
                        'L3_EN' => 'Sensory Play',
                        'L3_ZH' => '感官游戏',
                        'number' => 0,
                        'item_name' => 'Retry Product',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Retry Category',
            'slug' => 'retry-category',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'RETRY-1',
            'name' => 'Retry Product',
            'description' => 'Retry description',
            'image_url' => 'https://cdn.example.com/main.jpg',
            'source_image_url' => 'https://cdn.example.com/main.jpg',
            'source_platform' => '1688',
            'source_product_id' => 'retry-1',
            'source_url' => 'https://detail.1688.com/offer/retry-1.html',
            'import_status' => 'failed',
            'import_error' => 'Old failure',
            'moq' => 1,
            'lead_time_min_days' => 1,
            'lead_time_max_days' => 2,
            'stock_quantity' => 10,
            'base_price' => 9.99,
            'source_payload' => [
                'title' => 'Retry Product',
                'platform' => '1688',
                'num_iid' => 'retry-1',
                'detail_url' => 'https://detail.1688.com/offer/retry-1.html',
                'image_url' => 'https://cdn.example.com/main.jpg',
                'main_image_url' => 'https://cdn.example.com/main.jpg',
                'description' => 'Retry description',
                'images' => [
                    'https://cdn.example.com/gallery-1.jpg',
                ],
                'description_images' => [
                    'https://cdn.example.com/desc-1.jpg',
                ],
                'variants' => [
                    [
                        'sku_id' => 'sku-1',
                        'label' => 'Variant 1',
                        'image_url' => 'https://cdn.example.com/variant-1.jpg',
                        'stock_quantity' => 10,
                        'option_values' => [
                            [
                                'key' => '0:0',
                                'group_name' => '颜色',
                                'value' => 'Blue',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/products/{$product->id}/retry-import")
            ->assertOk()
            ->assertJsonPath('message', 'Product media reprocessing has been queued.');

        $product->refresh()->load('productImages');

        $this->assertSame('completed', $product->import_status);
        $this->assertNotNull($product->cat_from_api);
        $this->assertGreaterThanOrEqual(2, $product->productImages->count());
    }
}
