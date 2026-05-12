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
        $hasAttribute = fn (string $attribute) => array_key_exists($attribute, $this->resource->getAttributes());

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'category_slug' => $this->category?->parent?->slug ?? $this->category?->slug,
            'subcategory_slug' => $this->category?->parent ? $this->category?->slug : null,
            'image' => ProductImageUrl::fromStoredPath($this->image_url, false),
            'image_source_url' => $this->source_image_url ?? $this->image_url,
            'cat_from_api' => $this->cat_from_api,
            'moq' => $this->moq,
            'lead_time' => $this->formatted_lead_time,
            'verified' => $this->is_verified,
            'customizable' => $this->is_customizable,
            'stock_quantity' => $this->stock_quantity,
            'status' => $this->stock_quantity <= 1000 ? 'low-stock' : 'active',
            'import_status' => $this->import_status,
            'import_error' => $this->when($hasAttribute('import_error'), $this->import_error),
            'import_total_tasks' => $this->import_total_tasks,
            'import_completed_tasks' => $this->import_completed_tasks,
            'last_updated' => $this->updated_at?->toDateString(),
            'unit_price' => $tierOne ? (float) $tierOne->price : (float) $this->base_price,
            'base_price_range' => $this->price_range,
            'price_tier_1' => $tierOne ? ['range' => $tierOne->label, 'price' => (float) $tierOne->price] : null,
            'price_tier_2' => $tierTwo ? ['range' => $tierTwo->label, 'price' => (float) $tierTwo->price] : null,
        ];
    }
}
