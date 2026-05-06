<?php

namespace App\Http\Resources;

use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $galleryImages = $this->galleryPaths()
            ->map(fn (string $path) => ProductImageUrl::fromStoredPath($path))
            ->filter()
            ->values()
            ->all();
        $descriptionImages = $this->descriptionImagePaths()
            ->map(fn (string $path) => ProductImageUrl::fromStoredPath($path))
            ->filter()
            ->values()
            ->all();
        $variants = $this->variants
            ->map(function ($variant) {
                $optionValues = collect($variant->option_values ?? [])
                    ->filter(fn ($option) => is_array($option))
                    ->values()
                    ->all();

                return [
                    'id' => $variant->id,
                    'sku_id' => $variant->source_sku_id,
                    'properties_key' => $variant->source_properties_key,
                    'properties_name' => $variant->source_properties_name,
                    'label' => $variant->label,
                    'image' => ProductImageUrl::fromStoredPath($variant->image_url ?? $variant->source_image_url),
                    'price' => (float) $variant->price,
                    'original_price' => $variant->original_price !== null ? (float) $variant->original_price : null,
                    'stock_quantity' => $variant->stock_quantity,
                    'is_default' => $variant->is_default,
                    'option_values' => $optionValues,
                ];
            })
            ->values();
        $optionGroups = $variants
            ->flatMap(function (array $variant) {
                return collect($variant['option_values'])->map(function (array $option) use ($variant) {
                    return [
                        'group_name' => $option['group_name'],
                        'key' => $option['key'],
                        'value' => $option['value'],
                        'image' => $variant['image'],
                    ];
                });
            })
            ->groupBy('group_name')
            ->map(function ($options, $groupName) {
                return [
                    'name' => $groupName,
                    'values' => collect($options)
                        ->unique('key')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
        $defaultVariantId = $variants->firstWhere('is_default', true)['id'] ?? $variants->first()['id'] ?? null;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category?->name,
            'category_slug' => $this->category?->parent?->slug ?? $this->category?->slug,
            'description' => $this->description,
            'images' => $galleryImages,
            'description_images' => $descriptionImages,
            'image_source_url' => $this->source_image_url ?? $this->image_url,
            'import_status' => $this->import_status,
            'base_price' => (float) $this->base_price,
            'moq' => $this->moq,
            'lead_time' => $this->formatted_lead_time,
            'in_stock' => $this->stock_quantity > 0,
            'stock_quantity' => $this->stock_quantity,
            'is_verified' => $this->is_verified,
            'is_customizable' => $this->is_customizable,
            'default_variant_id' => $defaultVariantId,
            'option_groups' => $optionGroups,
            'variants' => $variants->all(),
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
