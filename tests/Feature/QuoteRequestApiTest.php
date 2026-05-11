<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProcurementListItem;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_quote_request_from_selected_procurement_items(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Classroom Supplies',
            'slug' => 'classroom-supplies',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SCI-001',
            'name' => 'Science Kit',
            'description' => 'STEM experiment kit.',
            'image_url' => 'products/science-kit.jpg',
            'moq' => 10,
            'lead_time_min_days' => 7,
            'lead_time_max_days' => 14,
            'stock_quantity' => 200,
            'base_price' => 12.50,
            'cat_from_api' => ['L1_EN' => 'Toys & Games'],
        ]);
        $product->priceTiers()->create([
            'min_quantity' => 10,
            'max_quantity' => null,
            'price' => 10.00,
        ]);
        $variant = $product->variants()->create([
            'source_sku_id' => 'SKU-BLUE',
            'source_properties_key' => '0:0',
            'source_properties_name' => '0:0:Color:Blue',
            'label' => 'Blue',
            'option_values' => [
                ['key' => '0:0', 'group_name' => 'Color', 'value' => 'Blue'],
            ],
            'image_url' => 'products/blue-kit.jpg',
            'price' => 10.00,
            'stock_quantity' => 50,
            'sort_order' => 0,
        ]);
        $item = ProcurementListItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_sku_id' => 'SKU-BLUE',
            'variant_label' => 'Blue',
            'variant_image_url' => 'products/blue-kit.jpg',
            'variant_options' => [
                ['key' => '0:0', 'group_name' => 'Color', 'value' => 'Blue'],
            ],
            'quantity' => 25,
            'unit_price' => 10.00,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/quote-requests', [
                'item_ids' => [$item->id],
                'notes' => 'Please quote freight separately.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.total_items', 25)
            ->assertJsonPath('data.subtotal', 250)
            ->assertJsonPath('data.items.0.product_name', 'Science Kit')
            ->assertJsonPath('data.items.0.variant_label', 'Blue')
            ->assertJsonPath('data.items.0.product_snapshot.product.name', 'Science Kit')
            ->assertJsonPath('data.items.0.product_snapshot.variant.source_sku_id', 'SKU-BLUE');

        $this->assertDatabaseHas('quote_requests', [
            'user_id' => $user->id,
            'status' => 'submitted',
            'total_items' => 25,
            'subtotal' => 250,
        ]);
        $this->assertDatabaseHas('quote_request_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Science Kit',
            'quantity' => 25,
            'line_total' => 250,
        ]);
        $this->assertDatabaseMissing('procurement_list_items', [
            'id' => $item->id,
        ]);
    }

    public function test_user_cannot_update_quote_request_status(): void
    {
        $user = User::factory()->create();
        $quoteRequest = QuoteRequest::query()->create([
            'user_id' => $user->id,
            'reference' => 'QUOTE-2026-ABC12345',
            'status' => 'submitted',
            'total_items' => 10,
            'subtotal' => 100,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/quote-requests/{$quoteRequest->id}", ['status' => 'rejected'])
            ->assertNotFound();

        $this->assertDatabaseHas('quote_requests', [
            'id' => $quoteRequest->id,
            'status' => 'submitted',
        ]);
    }

    public function test_admin_can_view_and_update_quote_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create([
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.com',
            'organization_name' => 'Northwind Traders',
        ]);
        $quoteRequest = QuoteRequest::query()->create([
            'user_id' => $customer->id,
            'reference' => 'QUOTE-2026-ADMIN1',
            'status' => 'submitted',
            'total_items' => 30,
            'subtotal' => 300,
        ]);
        QuoteRequest::query()->create([
            'user_id' => $customer->id,
            'reference' => 'QUOTE-2026-ADMIN2',
            'status' => 'submitted',
            'total_items' => 10,
            'subtotal' => 100,
        ]);
        $quoteRequest->items()->create([
            'product_name' => 'Training Hurdle',
            'product_sku' => 'TRAIN-001',
            'quantity' => 30,
            'unit_price' => 10,
            'line_total' => 300,
            'product_snapshot' => ['product' => ['name' => 'Training Hurdle']],
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/quote-requests?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.user.name', 'Ayesha Khan')
            ->assertJsonPath('data.0.reference', 'QUOTE-2026-ADMIN1');

        $this->withToken($token)
            ->getJson('/api/admin/quote-requests?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'QUOTE-2026-ADMIN2');

        $this->withToken($token)
            ->patchJson("/api/admin/quote-requests/{$quoteRequest->id}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.status_label', 'Accepted');

        $this->assertDatabaseHas('quote_requests', [
            'id' => $quoteRequest->id,
            'status' => 'accepted',
        ]);
    }
}
