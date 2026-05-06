<?php

namespace App\Http\Resources;

use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'variant_sku_id' => $this->variant_sku_id,
            'variant_label' => $this->variant_label,
            'variant_options' => $this->variant_options ?? [],
            'category' => $this->product?->category?->name,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'image' => ProductImageUrl::fromStoredPath($this->variant_image_url ?? $this->product?->image_url),
            'moq' => $this->product?->moq,
            'line_total' => round($this->quantity * $this->unit_price, 2),
        ];
    }
}
