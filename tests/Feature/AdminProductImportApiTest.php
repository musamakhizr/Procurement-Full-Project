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
                    'desc' => '<div><img src="https://cbu01.alicdn.com/img/ibank/desc-1.jpg" /></div>',
                    'props' => [
                        ['name' => 'Material', 'value' => 'Plush'],
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
            ->assertJsonPath('product.processed_main_image', null);
    }

    public function test_admin_can_create_imported_product_and_store_processed_images_locally(): void
    {
        Storage::fake('public');
        config()->set('services.fogot.base_url', 'https://py.fogot.cn/api/product');

        Http::fake([
            'https://py.fogot.cn/api/product/image/redraw' => Http::response([
                'body' => [
                    'images' => [
                        [
                            'mime_type' => 'image/jpeg',
                            'data' => base64_encode('processed-main-image'),
                        ],
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/translate' => Http::response([
                'images' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode('processed-gallery-image'),
                    ],
                ],
            ], 200),
            'https://py.fogot.cn/api/product/detail/image/classify' => Http::response([
                'category' => 'Imported Toys',
            ], 200),
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
        $this->assertSame('completed', $product->import_status);
        $this->assertCount(3, $product->productImages);
        $this->assertCount(2, $product->productImages->where('section', 'gallery'));
        $this->assertCount(1, $product->productImages->where('section', 'description'));
        $this->assertCount(1, $product->variants);
        $this->assertSame('香芋紫-15升', $product->variants->first()->label);

        foreach ($product->productImages as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }
}
