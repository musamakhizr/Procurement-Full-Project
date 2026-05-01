<?php

namespace App\Http\Resources;

use App\Support\RemoteImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'category' => $this->product?->category?->name,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'image' => RemoteImage::proxiedUrl($this->product?->image_url),
            'moq' => $this->product?->moq,
            'line_total' => round($this->quantity * $this->unit_price, 2),
        ];
    }
}
