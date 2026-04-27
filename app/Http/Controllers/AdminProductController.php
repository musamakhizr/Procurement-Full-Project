<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_products' => Product::query()->count(),
            'active_products' => Product::query()->where('is_active', true)->count(),
            'low_stock' => Product::query()->where('stock_quantity', '<=', 1000)->count(),
            'categories' => Category::query()->whereNull('parent_id')->count(),
        ]);
    }

    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category.parent', 'priceTiers'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return ProductListResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::query()->create([
            'category_id' => $request->integer('category_id'),
            'sku' => $request->string('sku')->toString(),
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->toString(),
            'image_url' => $request->input('image_url'),
            'moq' => $request->integer('moq'),
            'lead_time_min_days' => $request->integer('lead_time_min_days'),
            'lead_time_max_days' => $request->integer('lead_time_max_days'),
            'stock_quantity' => $request->integer('stock_quantity'),
            'is_verified' => $request->boolean('is_verified'),
            'is_customizable' => $request->boolean('is_customizable'),
            'is_active' => $request->boolean('is_active', true),
            'base_price' => $request->input('base_price'),
        ]);

        $this->syncPriceTiers($product, $request->input('price_tiers', []));
        $product->load(['category.parent', 'priceTiers']);

        return (new ProductDetailResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->safe()->except('price_tiers'));

        if ($request->has('price_tiers')) {
            $this->syncPriceTiers($product, $request->input('price_tiers', []));
        }

        $product->load(['category.parent', 'priceTiers']);

        return new ProductDetailResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    private function syncPriceTiers(Product $product, array $tiers): void
    {
        $product->priceTiers()->delete();

        foreach ($tiers as $tier) {
            $product->priceTiers()->create([
                'min_quantity' => $tier['min_quantity'],
                'max_quantity' => $tier['max_quantity'] ?? null,
                'price' => $tier['price'],
            ]);
        }
    }
}
