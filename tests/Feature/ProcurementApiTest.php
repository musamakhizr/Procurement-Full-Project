<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'organization_name' => 'Sunrise School',
            'organization_type' => 'school',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'organization_name', 'organization_type', 'role', 'is_admin'],
            ]);
    }

    public function test_authenticated_user_can_manage_procurement_list_items(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Office Supplies',
            'slug' => 'office',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'description' => 'A test product.',
            'moq' => 5,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 100,
            'base_price' => 12.50,
            'import_status' => 'completed',
        ]);

        $product->priceTiers()->createMany([
            ['min_quantity' => 1, 'max_quantity' => 4, 'price' => 12.50],
            ['min_quantity' => 5, 'max_quantity' => null, 'price' => 10.00],
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('quantity', 5);

        $this->withToken($token)
            ->getJson('/api/procurement-list')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_authenticated_user_can_add_a_specific_variant_to_procurement_list(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Bags',
            'slug' => 'bags',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'TB-611914001781',
            'name' => 'Taobao Backpack',
            'description' => 'Imported backpack.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 100,
            'base_price' => 69.90,
            'import_status' => 'completed',
        ]);

        $variant = $product->variants()->create([
            'source_sku_id' => '4479881078464',
            'source_properties_key' => '1627207:21287481607',
            'source_properties_name' => '1627207:21287481607:颜色分类:冰雾粉-15升',
            'label' => '冰雾粉-15升',
            'option_values' => [
                [
                    'key' => '1627207:21287481607',
                    'group_name' => '颜色分类',
                    'value' => '冰雾粉-15升',
                ],
            ],
            'price' => 69.90,
            'original_price' => 69.90,
            'stock_quantity' => 200,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('product_variant_id', $variant->id)
            ->assertJsonPath('variant_label', '冰雾粉-15升')
            ->assertJsonPath('unit_price', 69.9);
    }

    public function test_user_cannot_add_zero_stock_product_or_variant_to_procurement_list(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Bags',
            'slug' => 'bags',
            'sort_order' => 1,
        ]);
        $outOfStockProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'OOS-PRODUCT',
            'name' => 'Out of Stock Product',
            'description' => 'No stock.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 0,
            'base_price' => 10,
            'import_status' => 'completed',
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'VARIANT-STOCK',
            'name' => 'Variant Stock Product',
            'description' => 'Has one empty variant.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 100,
            'base_price' => 10,
            'import_status' => 'completed',
        ]);
        $outOfStockVariant = $product->variants()->create([
            'source_sku_id' => 'EMPTY-001',
            'label' => 'Empty',
            'option_values' => [['key' => '0:0', 'group_name' => 'Color', 'value' => 'Empty']],
            'price' => 10,
            'stock_quantity' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $outOfStockProduct->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This product is out of stock.');

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $product->id,
                'product_variant_id' => $outOfStockVariant->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This product variation is out of stock.');
    }

    public function test_variant_product_requires_available_variant_even_when_product_stock_is_zero(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Bags',
            'slug' => 'bags',
            'sort_order' => 1,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'VARIANT-ONLY-STOCK',
            'name' => 'Variant Only Stock Product',
            'description' => 'Stock comes from variants.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 0,
            'base_price' => 10,
            'import_status' => 'completed',
        ]);
        $availableVariant = $product->variants()->create([
            'source_sku_id' => 'AVAILABLE-001',
            'label' => 'Available',
            'option_values' => [['key' => '0:1', 'group_name' => 'Color', 'value' => 'Available']],
            'price' => 12,
            'stock_quantity' => 5,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please select an available product variation.');

        $this->withToken($token)
            ->postJson('/api/procurement-list', [
                'product_id' => $product->id,
                'product_variant_id' => $availableVariant->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('product_variant_id', $availableVariant->id);
    }
}
