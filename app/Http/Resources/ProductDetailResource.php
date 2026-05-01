<?php

namespace App\Http\Resources;

use App\Support\RemoteImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayImageUrl = RemoteImage::proxiedUrl($this->image_url);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'category_slug' => $this->category?->parent?->slug ?? $this->category?->slug,
            'description' => $this->description,
            'images' => array_values(array_filter([$displayImageUrl])),
            'image_source_url' => $this->image_url,
            'moq' => $this->moq,
            'lead_time' => $this->formatted_lead_time,
            'in_stock' => $this->stock_quantity > 0,
            'stock_quantity' => $this->stock_quantity,
            'is_verified' => $this->is_verified,
            'is_customizable' => $this->is_customizable,
            'pricing_tiers' => $this->priceTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'min_qty' => $tier->min_quantity,
                'max_qty' => $tier->max_quantity,
                'price' => (float) $tier->price,
                'label' => $tier->label,
            ]),
            'specifications' => [
                ['label' => 'SKU', 'value' => $this->sku],
                ['label' => 'MOQ', 'value' => (string) $this->moq],
                ['label' => 'Lead Time', 'value' => $this->formatted_lead_time],
                ['label' => 'Verified Supplier', 'value' => $this->is_verified ? 'Yes' : 'No'],
                ['label' => 'Customizable', 'value' => $this->is_customizable ? 'Yes' : 'No'],
            ],
        ];
    }
}
