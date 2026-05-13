<?php

namespace App\Http\Controllers;

use App\Jobs\ImportMarketplaceProductsFromSpreadsheet;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImportedProductSyncService;
use App\Services\SpreadsheetProductLinkExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly ImportedProductSyncService $importedProductSyncService,
    ) {
    }

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
            ->with(['category.parent', 'priceTiers', 'productImages'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('cat_from_api', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return ProductListResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $importSource = $request->input('import_source');

            $product = Product::query()->create([
                'category_id' => $request->integer('category_id'),
                'sku' => $request->string('sku')->toString(),
                'name' => $request->string('name')->toString(),
                'description' => $request->string('description')->toString(),
                'image_url' => $request->input('image_url'),
                'source_platform' => data_get($importSource, 'platform'),
                'source_product_id' => data_get($importSource, 'num_iid'),
                'source_url' => data_get($importSource, 'detail_url'),
                'source_image_url' => data_get($importSource, 'image_url'),
                'source_category_label' => data_get($importSource, 'classified_category'),
                'cat_from_api' => null,
                'import_status' => is_array($importSource) ? 'pending' : null,
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
            $this->importedProductSyncService->syncVariants($product, is_array($importSource) ? $importSource : null);

            return $product;
        });

        $this->importedProductSyncService->schedule($product, $request->input('import_source'));

        $product->load(['category.parent', 'priceTiers', 'productImages', 'variants']);

        return (new ProductDetailResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function importSpreadsheet(Request $request, SpreadsheetProductLinkExtractor $linkExtractor): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ]);

        try {
            $links = $linkExtractor->extractFromUploadedFile($validated['file']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($links === []) {
            return response()->json([
                'message' => 'No supported Taobao, 1688, Tmall, or JD product links were found in this file.',
            ], 422);
        }

        ImportMarketplaceProductsFromSpreadsheet::dispatch($links, $request->user()?->getKey());

        return response()->json([
            'message' => 'Product import has started. Products will be fetched, inserted, and processed one by one in the background.',
            'queued_count' => count($links),
            'links' => $links,
        ], 202);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except(['price_tiers', 'import_source']));

            if ($request->has('import_source')) {
                $importSource = $request->input('import_source');

                $product->forceFill([
                    'source_platform' => data_get($importSource, 'platform'),
                    'source_product_id' => data_get($importSource, 'num_iid'),
                    'source_url' => data_get($importSource, 'detail_url'),
                    'source_image_url' => data_get($importSource, 'image_url'),
                    'source_category_label' => data_get($importSource, 'classified_category'),
                    'cat_from_api' => null,
                    'import_status' => 'pending',
                    'import_error' => null,
                ])->save();

                $this->importedProductSyncService->syncVariants($product, is_array($importSource) ? $importSource : null);
            }

            if ($request->has('price_tiers')) {
                $this->syncPriceTiers($product, $request->input('price_tiers', []));
            }
        });

        if ($request->has('import_source')) {
            $this->importedProductSyncService->schedule($product, $request->input('import_source'));
        }

        $product->load(['category.parent', 'priceTiers', 'productImages', 'variants']);

        return new ProductDetailResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function retryImport(Product $product)
    {
        $sourcePayload = $product->source_payload;

        if (! is_array($sourcePayload) || $sourcePayload === []) {
            return response()->json([
                'message' => 'This product does not have an import payload to retry.',
            ], 422);
        }

        $this->importedProductSyncService->syncVariants($product, $sourcePayload);
        $this->importedProductSyncService->schedule($product, $sourcePayload);

        $product->refresh()->load(['category.parent', 'priceTiers', 'productImages', 'variants']);

        return response()->json([
            'message' => 'Product media reprocessing has been queued.',
            'product' => new ProductDetailResource($product),
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
