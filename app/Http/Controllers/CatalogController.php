<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    private const PRODUCT_LIST_COLUMNS = [
        'id',
        'category_id',
        'sku',
        'name',
        'image_url',
        'source_image_url',
        'cat_from_api',
        'moq',
        'lead_time_min_days',
        'lead_time_max_days',
        'stock_quantity',
        'is_verified',
        'is_customizable',
        'is_active',
        'base_price',
        'import_status',
        'import_total_tasks',
        'import_completed_tasks',
        'updated_at',
    ];

    public function categories()
    {
        $categories = Cache::remember('catalog:categories:v1', now()->addMinutes(10), function () {
            return Category::query()
                ->select(['id', 'parent_id', 'name', 'slug', 'sort_order'])
                ->whereNull('parent_id')
                ->with(['children:id,parent_id,name,slug,sort_order'])
                ->orderBy('sort_order')
                ->get();
        });

        return CategoryResource::collection($categories);
    }

    public function products(Request $request)
    {
        $sort = $request->string('sort')->toString();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(24, max(1, (int) $request->input('per_page', 12)));
        $cacheKey = $this->catalogProductsCacheKey($request, $sort, $page, $perPage);

        $products = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($request, $sort, $page, $perPage) {
            return Product::query()
                ->select(self::PRODUCT_LIST_COLUMNS)
                ->with([
                    'category:id,parent_id,name,slug',
                    'category.parent:id,parent_id,name,slug',
                    'priceTiers:id,product_id,min_quantity,max_quantity,price',
                ])
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
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('cat_from_api', 'like', "%{$search}%");
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
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString();
        });

        return ProductListResource::collection($products);
    }

    public function show(Product $product): ProductDetailResource
    {
        $product->load(['category.parent', 'priceTiers', 'productImages', 'variants']);

        return new ProductDetailResource($product);
    }

    private function catalogProductsCacheKey(Request $request, string $sort, int $page, int $perPage): string
    {
        $filters = [
            'category' => $request->string('category')->toString(),
            'subcategory' => $request->string('subcategory')->toString(),
            'search' => $request->string('search')->toString(),
            'moq_max' => $request->input('moq_max'),
            'lead_time_max' => $request->input('lead_time_max'),
            'verified_only' => $request->boolean('verified_only'),
            'customizable_only' => $request->boolean('customizable_only'),
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ];

        return 'catalog:products:v2:'.md5(json_encode($filters));
    }
}
