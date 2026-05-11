<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequests\StoreQuoteRequestRequest;
use App\Http\Resources\QuoteRequestResource;
use App\Models\ProcurementListItem;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteRequestController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        $quoteRequests = QuoteRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate($perPage);

        return QuoteRequestResource::collection($quoteRequests);
    }

    public function store(StoreQuoteRequestRequest $request)
    {
        $selectedIds = collect($request->input('item_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $procurementItems = ProcurementListItem::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $selectedIds)
            ->with(['product.category.parent', 'product.priceTiers', 'variant'])
            ->get()
            ->sortBy(fn (ProcurementListItem $item) => $selectedIds->search($item->id))
            ->values();

        if ($procurementItems->count() !== $selectedIds->count()) {
            throw ValidationException::withMessages([
                'item_ids' => 'One or more selected procurement list items are unavailable.',
            ]);
        }

        $quoteRequest = DB::transaction(function () use ($request, $procurementItems) {
            $subtotal = $procurementItems->sum(fn (ProcurementListItem $item) => $this->lineTotal($item));
            $totalItems = $procurementItems->sum('quantity');

            $quoteRequest = QuoteRequest::query()->create([
                'user_id' => $request->user()->id,
                'reference' => QuoteRequest::generateReference(),
                'status' => 'submitted',
                'total_items' => $totalItems,
                'subtotal' => $subtotal,
                'notes' => $request->input('notes'),
            ]);

            foreach ($procurementItems as $item) {
                $product = $item->product;
                $variant = $item->variant;

                $quoteRequest->items()->create([
                    'procurement_list_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => (string) $product?->name,
                    'product_sku' => $product?->sku,
                    'category_name' => $this->categoryName($item),
                    'image_url' => $item->variant_image_url ?? $product?->image_url,
                    'variant_sku_id' => $item->variant_sku_id ?? $variant?->source_sku_id,
                    'variant_label' => $item->variant_label ?? $variant?->label,
                    'variant_options' => $item->variant_options ?? $variant?->option_values ?? [],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $this->lineTotal($item),
                    'moq' => $product?->moq,
                    'product_snapshot' => $this->buildProductSnapshot($item),
                ]);
            }

            ProcurementListItem::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('id', $procurementItems->pluck('id'))
                ->delete();

            return $quoteRequest->load('items');
        });

        return response()->json([
            'message' => 'Quote request submitted successfully.',
            'data' => new QuoteRequestResource($quoteRequest),
        ], 201);
    }

    private function lineTotal(ProcurementListItem $item): float
    {
        return round($item->quantity * (float) $item->unit_price, 2);
    }

    private function categoryName(ProcurementListItem $item): ?string
    {
        $category = $item->product?->category;

        if ($category === null) {
            return null;
        }

        return $category->parent
            ? "{$category->parent->name} / {$category->name}"
            : $category->name;
    }

    private function buildProductSnapshot(ProcurementListItem $item): array
    {
        $product = $item->product;
        $variant = $item->variant;

        return [
            'product' => [
                'id' => $product?->id,
                'sku' => $product?->sku,
                'name' => $product?->name,
                'description' => $product?->description,
                'category' => $this->categoryName($item),
                'image_url' => $product?->image_url,
                'source_url' => $product?->source_url,
                'source_platform' => $product?->source_platform,
                'source_product_id' => $product?->source_product_id,
                'source_payload' => $product?->source_payload,
                'cat_from_api' => $product?->cat_from_api,
                'moq' => $product?->moq,
                'lead_time_min_days' => $product?->lead_time_min_days,
                'lead_time_max_days' => $product?->lead_time_max_days,
                'stock_quantity' => $product?->stock_quantity,
                'base_price' => $product ? (float) $product->base_price : null,
                'is_verified' => $product?->is_verified,
                'is_customizable' => $product?->is_customizable,
            ],
            'price_tiers' => $product?->priceTiers
                ? $product->priceTiers->map(fn ($tier) => [
                    'min_quantity' => $tier->min_quantity,
                    'max_quantity' => $tier->max_quantity,
                    'price' => (float) $tier->price,
                ])->values()->all()
                : [],
            'variant' => [
                'id' => $variant?->id,
                'source_sku_id' => $item->variant_sku_id ?? $variant?->source_sku_id,
                'source_properties_key' => $variant?->source_properties_key,
                'source_properties_name' => $variant?->source_properties_name,
                'label' => $item->variant_label ?? $variant?->label,
                'option_values' => $item->variant_options ?? $variant?->option_values ?? [],
                'image_url' => $item->variant_image_url ?? $variant?->image_url,
                'source_image_url' => $variant?->source_image_url,
                'price' => $variant ? (float) $variant->price : null,
                'original_price' => $variant?->original_price !== null ? (float) $variant->original_price : null,
                'stock_quantity' => $variant?->stock_quantity,
            ],
            'request_item' => [
                'procurement_list_item_id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => $this->lineTotal($item),
            ],
        ];
    }
}
