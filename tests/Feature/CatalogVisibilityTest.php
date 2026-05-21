<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_shows_completed_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'sort_order' => 1,
        ]);

        $completedProduct = $this->createProduct($category, 'DONE-1', 'Completed Imported Product', 'completed');
        $pendingProduct = $this->createProduct($category, 'PENDING-1', 'Pending Imported Product', 'pending');
        $processingProduct = $this->createProduct($category, 'PROCESSING-1', 'Processing Imported Product', 'processing');
        $failedProduct = $this->createProduct($category, 'FAILED-1', 'Failed Imported Product', 'failed');

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $completedProduct->id])
            ->assertJsonMissing(['id' => $pendingProduct->id])
            ->assertJsonMissing(['id' => $processingProduct->id])
            ->assertJsonMissing(['id' => $failedProduct->id]);

        $this->getJson("/api/products/{$pendingProduct->id}")
            ->assertNotFound();

        $this->getJson("/api/products/{$processingProduct->id}")
            ->assertNotFound();
    }

    public function test_admin_product_index_shows_pending_processing_and_completed_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create([
            'name' => 'Marketplace',
            'slug' => 'marketplace',
            'sort_order' => 1,
        ]);

        $completedProduct = $this->createProduct($category, 'DONE-1', 'Completed Imported Product', 'completed');
        $pendingProduct = $this->createProduct($category, 'PENDING-1', 'Pending Imported Product', 'pending');
        $processingProduct = $this->createProduct($category, 'PROCESSING-1', 'Processing Imported Product', 'processing');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $completedProduct->id, 'import_status' => 'completed'])
            ->assertJsonFragment(['id' => $pendingProduct->id, 'import_status' => 'pending'])
            ->assertJsonFragment(['id' => $processingProduct->id, 'import_status' => 'processing']);

        $this->withToken($admin->createToken('test-detail')->plainTextToken)
            ->getJson("/api/admin/products/{$pendingProduct->id}")
            ->assertOk()
            ->assertJsonPath('id', $pendingProduct->id)
            ->assertJsonPath('import_status', 'pending');

        $this->withToken($admin->createToken('test-processing-detail')->plainTextToken)
            ->getJson("/api/admin/products/{$processingProduct->id}")
            ->assertOk()
            ->assertJsonPath('id', $processingProduct->id)
            ->assertJsonPath('import_status', 'processing');
    }

    public function test_admin_catalog_shows_pending_processing_and_completed_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::query()->create([
            'name' => 'Marketplace',
            'slug' => 'marketplace',
            'sort_order' => 1,
        ]);

        $completedProduct = $this->createProduct($category, 'CAT-DONE-1', 'Completed Catalog Product', 'completed');
        $pendingProduct = $this->createProduct($category, 'CAT-PENDING-1', 'Pending Catalog Product', 'pending');
        $processingProduct = $this->createProduct($category, 'CAT-PROCESSING-1', 'Processing Catalog Product', 'processing');

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $completedProduct->id])
            ->assertJsonMissing(['id' => $pendingProduct->id])
            ->assertJsonMissing(['id' => $processingProduct->id]);

        $this->withToken($customer->createToken('customer-catalog')->plainTextToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $completedProduct->id])
            ->assertJsonMissing(['id' => $pendingProduct->id])
            ->assertJsonMissing(['id' => $processingProduct->id]);

        $this->withToken($admin->createToken('admin-catalog')->plainTextToken)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $completedProduct->id, 'import_status' => 'completed'])
            ->assertJsonFragment(['id' => $pendingProduct->id, 'import_status' => 'pending'])
            ->assertJsonFragment(['id' => $processingProduct->id, 'import_status' => 'processing']);

        $this->withToken($admin->createToken('admin-catalog-detail')->plainTextToken)
            ->getJson("/api/products/{$pendingProduct->id}")
            ->assertOk()
            ->assertJsonPath('id', $pendingProduct->id)
            ->assertJsonPath('import_status', 'pending');
    }

    public function test_public_catalog_stock_uses_available_variant_stock(): void
    {
        $category = Category::query()->create([
            'name' => 'Bags',
            'slug' => 'bags',
            'sort_order' => 1,
        ]);

        $partiallyAvailableProduct = $this->createProduct($category, 'PARTIAL-1', 'Partial Variant Product', 'completed', 0);
        $partiallyAvailableProduct->variants()->createMany([
            [
                'label' => 'Empty',
                'option_values' => [['key' => '0:0', 'group_name' => 'Color', 'value' => 'Empty']],
                'price' => 10,
                'stock_quantity' => 0,
                'sort_order' => 0,
            ],
            [
                'label' => 'Available',
                'option_values' => [['key' => '0:1', 'group_name' => 'Color', 'value' => 'Available']],
                'price' => 12,
                'stock_quantity' => 7,
                'sort_order' => 1,
            ],
        ]);

        $outOfStockProduct = $this->createProduct($category, 'EMPTY-1', 'Empty Variant Product', 'completed', 20);
        $outOfStockProduct->variants()->createMany([
            [
                'label' => 'Empty',
                'option_values' => [['key' => '1:0', 'group_name' => 'Color', 'value' => 'Empty']],
                'price' => 10,
                'stock_quantity' => 0,
                'sort_order' => 0,
            ],
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $partiallyAvailableProduct->id,
                'stock_quantity' => 7,
                'has_variants' => true,
                'available_variants_count' => 1,
                'status' => 'low-stock',
            ])
            ->assertJsonFragment([
                'id' => $outOfStockProduct->id,
                'stock_quantity' => 0,
                'has_variants' => true,
                'available_variants_count' => 0,
                'status' => 'out-of-stock',
            ]);
    }

    private function createProduct(Category $category, string $sku, string $name, string $importStatus, int $stockQuantity = 100): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'sku' => $sku,
            'name' => $name,
            'description' => $name.' description.',
            'moq' => 1,
            'lead_time_min_days' => 2,
            'lead_time_max_days' => 4,
            'stock_quantity' => $stockQuantity,
            'base_price' => 10,
            'is_active' => true,
            'import_status' => $importStatus,
        ]);
    }
}
