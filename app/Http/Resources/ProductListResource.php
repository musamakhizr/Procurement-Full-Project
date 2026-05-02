<?php

namespace App\Http\Resources;

use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tierOne = $this->priceTiers->get(0);
        $tierTwo = $this->priceTiers->get(1) ?? $tierOne;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'category_slug' => $this->category?->parent?->slug ?? $this->category?->slug,
            'subcategory_slug' => $this->category?->parent ? $this->category?->slug : null,
            'image' => ProductImageUrl::fromStoredPath($this->image_url),
            'image_source_url' => $this->source_image_url ?? $this->image_url,
            'moq' => $this->moq,
            'lead_time' => $this->formatted_lead_time,
            'verified' => $this->is_verified,
            'customizable' => $this->is_customizable,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->stock_quantity <= 1000 ? 'low-stock' : 'active',
            'last_updated' => $this->updated_at?->toDateString(),
            'base_price_range' => $this->price_range,
            'price_tier_1' => $tierOne ? ['range' => $tierOne->label, 'price' => (float) $tierOne->price] : null,
            'price_tier_2' => $tierTwo ? ['range' => $tierTwo->label, 'price' => (float) $tierTwo->price] : null,
        ];
    }
}
