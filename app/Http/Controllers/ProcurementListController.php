<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcurementList\StoreProcurementListItemRequest;
use App\Http\Requests\ProcurementList\UpdateProcurementListItemRequest;
use App\Http\Resources\ProcurementListItemResource;
use App\Models\ProcurementListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

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
        $product = Product::query()->with('priceTiers')->findOrFail($request->integer('product_id'));
        $variantId = $request->integer('product_variant_id') ?: null;
        $variant = $variantId
            ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId)
            : null;
        $quantity = max($request->integer('quantity') ?: $product->moq, $product->moq);

        $item = ProcurementListItem::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $variant ? (float) $variant->price : (float) $product->base_price,
                'variant_sku_id' => $variant?->source_sku_id,
                'variant_label' => $variant?->label,
                'variant_image_url' => $variant?->image_url ?? $variant?->source_image_url,
                'variant_options' => $variant?->option_values,
            ],
        );

        $item->load(['product.category.parent', 'variant']);

        return new ProcurementListItemResource($item);
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
}
