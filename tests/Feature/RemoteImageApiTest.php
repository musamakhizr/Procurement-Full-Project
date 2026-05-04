<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RemoteImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_image_proxy_serves_allowlisted_image_hosts(): void
    {
        $imageUrl = 'https://cbu01.alicdn.com/img/ibank/example.jpg';

        Http::fake([
            $imageUrl => Http::response('fake-image-bytes', 200, [
                'Content-Length' => '16',
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $signedUrl = URL::signedRoute('remote-images.show', [
            'url' => $imageUrl,
        ]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'max-age=86400, public, s-maxage=604800')
            ->assertSeeText('fake-image-bytes', false);
    }

    public function test_product_detail_returns_proxied_display_image_and_raw_source_image(): void
    {
        $category = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'AL-1688-1',
            'name' => 'Imported toy',
            'description' => 'Imported from 1688',
            'image_url' => 'https://cbu01.alicdn.com/img/ibank/example.jpg',
            'moq' => 5,
            'lead_time_min_days' => 3,
            'lead_time_max_days' => 7,
            'stock_quantity' => 50,
            'base_price' => 9.99,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('image_source_url', 'https://cbu01.alicdn.com/img/ibank/example.jpg')
            ->assertJsonPath('description_images', [])
            ->assertJsonPath('images.0', URL::signedRoute('remote-images.show', [
                'url' => 'https://cbu01.alicdn.com/img/ibank/example.jpg',
            ]));
    }

    public function test_import_preview_returns_display_image_url_for_proxy_hosts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        Http::fake([
            'https://api-gw.onebound.cn/1688/item_get/*' => Http::response([
                'item' => [
                    'title' => 'Imported item',
                    'price' => '5.50',
                    'detail_url' => 'https://detail.1688.com/offer/1032188551822.html',
                    'pic_url' => 'https://cbu01.alicdn.com/img/ibank/example.jpg',
                ],
            ]),
        ]);

        $this->withToken($token)
            ->getJson('/api/admin/products/from-link?link=https://detail.1688.com/offer/1032188551822.html')
            ->assertOk()
            ->assertJsonPath('product.image_url', 'https://cbu01.alicdn.com/img/ibank/example.jpg')
            ->assertJsonPath('product.display_image_url', URL::signedRoute('remote-images.show', [
                'url' => 'https://cbu01.alicdn.com/img/ibank/example.jpg',
            ]));
    }
}
