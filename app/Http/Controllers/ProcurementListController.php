<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcurementList\StoreProcurementListItemRequest;
use App\Http\Requests\ProcurementList\StoreBulkProcurementListItemsRequest;
use App\Http\Requests\ProcurementList\UpdateProcurementListItemRequest;
use App\Http\Resources\ProcurementListItemResource;
use App\Models\ProcurementListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementListController extends Controller
{
    public function index(Request $request)
    {
        $items = ProcurementListItem::query()
            ->where('user_id', $request->user()->id)
            ->with(['product.category.parent', 'variant'])
            ->latest()
            ->get();

        return ProcurementListItemResource::collection($items);
    }

    public function store(StoreProcurementListItemRequest $request)
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        $variantId = $request->integer('product_variant_id') ?: null;
        $variant = $variantId
            ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId)
            : null;
        $item = $this->upsertItem(
            $request->user()->id,
            $product,
            $variant,
            $request->integer('quantity') ?: $product->moq
        );

        $item->load(['product.category.parent', 'variant']);

        return new ProcurementListItemResource($item);
    }

    public function bulkStore(StoreBulkProcurementListItemsRequest $request)
    {
        $items = DB::transaction(function () use ($request) {
            return collect($request->input('items'))
                ->map(function (array $payload) use ($request) {
                    $product = Product::query()->findOrFail((int) $payload['product_id']);
                    $variantId = ! empty($payload['product_variant_id']) ? (int) $payload['product_variant_id'] : null;
                    $variant = $variantId
                        ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId)
                        : null;

                    return $this->upsertItem(
                        $request->user()->id,
                        $product,
                        $variant,
                        (int) $payload['quantity']
                    );
                })
                ->each->load(['product.category.parent', 'variant'])
                ->values();
        });

        return ProcurementListItemResource::collection($items)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProcurementListItemRequest $request, ProcurementListItem $procurementListItem)
    {
        abort_unless($procurementListItem->user_id === $request->user()->id, 403);

        $procurementListItem->loadMissing(['product.priceTiers', 'variant']);
        $quantity = max($request->integer('quantity'), $procurementListItem->product->moq);

        $procurementListItem->update([
            'quantity' => $quantity,
            'unit_price' => $procurementListItem->variant
                ? (float) $procurementListItem->variant->price
                : (float) $procurementListItem->product->base_price,
        ]);

        $procurementListItem->load(['product.category.parent', 'variant']);

        return new ProcurementListItemResource($procurementListItem);
    }

    public function destroy(Request $request, ProcurementListItem $procurementListItem)
    {
        abort_unless($procurementListItem->user_id === $request->user()->id, 403);

        $procurementListItem->delete();

        return response()->json([
            'message' => 'Item removed from procurement list.',
        ]);
    }

    private function upsertItem(int $userId, Product $product, ?ProductVariant $variant, int $quantity): ProcurementListItem
    {
        return ProcurementListItem::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'quantity' => max($quantity, $product->moq),
                'unit_price' => $variant ? (float) $variant->price : (float) $product->base_price,
                'variant_sku_id' => $variant?->source_sku_id,
                'variant_label' => $variant?->label,
                'variant_image_url' => $variant?->image_url ?? $variant?->source_image_url,
                'variant_options' => $variant?->option_values,
            ],
        );
    }
}
