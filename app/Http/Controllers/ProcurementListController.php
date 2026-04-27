<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcurementList\StoreProcurementListItemRequest;
use App\Http\Requests\ProcurementList\UpdateProcurementListItemRequest;
use App\Http\Resources\ProcurementListItemResource;
use App\Models\ProcurementListItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ProcurementListController extends Controller
{
    public function index(Request $request)
    {
        $items = ProcurementListItem::query()
            ->where('user_id', $request->user()->id)
            ->with('product.category.parent')
            ->latest()
            ->get();

        return ProcurementListItemResource::collection($items);
    }

    public function store(StoreProcurementListItemRequest $request)
    {
        $product = Product::query()->with('priceTiers')->findOrFail($request->integer('product_id'));
        $quantity = max($request->integer('quantity') ?: $product->moq, $product->moq);

        $item = ProcurementListItem::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => $quantity,
                'unit_price' => $product->priceForQuantity($quantity),
            ],
        );

        $item->load('product.category.parent');

        return new ProcurementListItemResource($item);
    }

    public function update(UpdateProcurementListItemRequest $request, ProcurementListItem $procurementListItem)
    {
        abort_unless($procurementListItem->user_id === $request->user()->id, 403);

        $procurementListItem->loadMissing('product.priceTiers');
        $quantity = max($request->integer('quantity'), $procurementListItem->product->moq);

        $procurementListItem->update([
            'quantity' => $quantity,
            'unit_price' => $procurementListItem->product->priceForQuantity($quantity),
        ]);

        $procurementListItem->load('product.category.parent');

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
