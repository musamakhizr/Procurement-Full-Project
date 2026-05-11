<?php

namespace App\Http\Resources;

use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'procurement_list_item_id' => $this->procurement_list_item_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'category_name' => $this->category_name,
            'image' => ProductImageUrl::fromStoredPath($this->image_url),
            'raw_image_url' => $this->image_url,
            'variant_sku_id' => $this->variant_sku_id,
            'variant_label' => $this->variant_label,
            'variant_options' => $this->variant_options ?? [],
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_total' => (float) $this->line_total,
            'moq' => $this->moq,
            'product_snapshot' => $this->product_snapshot ?? [],
        ];
    }
}
