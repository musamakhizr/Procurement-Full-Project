<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementBulkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_multiple_variants_to_procurement_list_at_once(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'MULTI-SKU-001',
            'name' => 'Multi SKU Toy',
            'description' => 'Imported toy with multiple variants.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => 100,
            'base_price' => 10.00,
        ]);

        $blueVariant = $product->variants()->create([
            'source_sku_id' => 'BLUE-001',
            'source_properties_key' => '0:0',
            'source_properties_name' => '0:0:Color:Blue',
            'label' => 'Blue',
            'option_values' => [['key' => '0:0', 'group_name' => 'Color', 'value' => 'Blue']],
            'price' => 11.00,
            'stock_quantity' => 20,
        ]);
        $redVariant = $product->variants()->create([
            'source_sku_id' => 'RED-001',
            'source_properties_key' => '0:1',
            'source_properties_name' => '0:1:Color:Red',
            'label' => 'Red',
            'option_values' => [['key' => '0:1', 'group_name' => 'Color', 'value' => 'Red']],
            'price' => 12.00,
            'stock_quantity' => 30,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/procurement-list/bulk', [
                'items' => [
                    ['product_id' => $product->id, 'product_variant_id' => $blueVariant->id, 'quantity' => 2],
                    ['product_id' => $product->id, 'product_variant_id' => $redVariant->id, 'quantity' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2)
            ->assertJsonPath('0.product_variant_id', $blueVariant->id)
            ->assertJsonPath('0.quantity', 2)
            ->assertJsonPath('1.product_variant_id', $redVariant->id)
            ->assertJsonPath('1.quantity', 3);

        $this->assertDatabaseHas('procurement_list_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $blueVariant->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('procurement_list_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $redVariant->id,
            'quantity' => 3,
        ]);
    }
}
