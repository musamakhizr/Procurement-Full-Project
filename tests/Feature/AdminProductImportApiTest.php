<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductImportApiTest extends TestCase
{
    use RefreshDatabase;

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
}

