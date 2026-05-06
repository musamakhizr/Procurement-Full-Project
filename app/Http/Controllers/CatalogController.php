<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories()
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function products(Request $request)
    {
        $sort = $request->string('sort')->toString();

        $products = Product::query()
            ->with(['category.parent', 'priceTiers', 'productImages'])
            ->where('is_active', true)
            ->when($request->filled('category'), function ($query) use ($request) {
                $categorySlug = $request->string('category')->toString();

                $query->whereHas('category', function ($categoryQuery) use ($categorySlug) {
                    $categoryQuery
                        ->where('slug', $categorySlug)
                        ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->where('slug', $categorySlug));
                });
            })
            ->when($request->filled('subcategory'), fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('subcategory')->toString())))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('verified_only'), fn ($query) => $query->where('is_verified', true))
            ->when($request->boolean('customizable_only'), fn ($query) => $query->where('is_customizable', true))
            ->when($request->filled('moq_max'), fn ($query) => $query->where('moq', '<=', (int) $request->input('moq_max')))
            ->when($request->filled('lead_time_max'), fn ($query) => $query->where('lead_time_max_days', '<=', (int) $request->input('lead_time_max')))
            ->when($sort === 'price_low', fn ($query) => $query->orderBy('base_price'))
            ->when($sort === 'price_high', fn ($query) => $query->orderByDesc('base_price'))
            ->when($sort === 'lead_time', fn ($query) => $query->orderBy('lead_time_max_days'))
            ->when($sort === 'moq', fn ($query) => $query->orderBy('moq'))
            ->when(! in_array($sort, ['price_low', 'price_high', 'lead_time', 'moq'], true), fn ($query) => $query->latest('updated_at'))
            ->paginate(12)
            ->withQueryString();

        return ProductListResource::collection($products);
    }

    public function show(Product $product): ProductDetailResource
    {
        $product->load(['category.parent', 'priceTiers', 'productImages', 'variants']);

        return new ProductDetailResource($product);
    }
}
