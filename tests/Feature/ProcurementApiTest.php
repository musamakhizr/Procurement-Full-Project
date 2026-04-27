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
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.quantity', 5);

        $this->withToken($token)
            ->getJson('/api/procurement-list')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
