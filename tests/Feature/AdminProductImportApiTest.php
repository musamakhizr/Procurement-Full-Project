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
            ->assertJsonCount(3, 'product.images')
            ->assertJsonPath('product.images.0', 'https://cbu01.alicdn.com/img/ibank/main.jpg')
            ->assertJsonPath('product.images.2', 'https://cbu01.alicdn.com/img/ibank/desc-1.jpg');
    }

    public function test_admin_can_create_imported_product_and_store_images_locally(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://cdn.example.com/*' => Http::response('fake-image-binary', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
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
                'description' => 'Imported description',
                'description_html' => '<p>Imported description</p>',
                'images' => [
                    'https://cdn.example.com/main.jpg',
                    'https://cdn.example.com/gallery-1.jpg',
                    'https://cdn.example.com/desc-1.jpg',
                ],
            ],
        ]);

        $product = Product::query()->with('productImages')->firstOrFail();

        $response
            ->assertCreated()
            ->assertJsonCount(3, 'images');

        $this->assertSame('1688', $product->source_platform);
        $this->assertSame('1032188551822', $product->source_product_id);
        $this->assertCount(3, $product->productImages);

        foreach ($product->productImages as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }
}
