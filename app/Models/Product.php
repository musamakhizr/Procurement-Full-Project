<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'image_url',
        'source_platform',
        'source_product_id',
        'source_url',
        'source_image_url',
        'source_category_label',
        'import_status',
        'import_error',
        'source_payload',
        'moq',
        'lead_time_min_days',
        'lead_time_max_days',
        'stock_quantity',
        'is_verified',
        'is_customizable',
        'is_active',
        'base_price',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_customizable' => 'boolean',
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
        'source_payload' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    protected function formattedLeadTime(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->lead_time_min_days}-{$this->lead_time_max_days} days",
        );
    }

    protected function priceRange(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->priceTiers->isNotEmpty()
                ? number_format((float) $this->priceTiers->min('price'), 2).' - '.number_format((float) $this->priceTiers->max('price'), 2)
                : number_format((float) $this->base_price, 2),
        );
    }

    public function priceForQuantity(int $quantity): float
    {
        $tier = $this->priceTiers
            ->first(fn (ProductPriceTier $tier) => $quantity >= $tier->min_quantity && ($tier->max_quantity === null || $quantity <= $tier->max_quantity));

        return (float) ($tier?->price ?? $this->base_price);
    }

    /**
     * @return Collection<int, string>
     */
    public function galleryPaths(): Collection
    {
        if ($this->relationLoaded('productImages') && $this->productImages->where('section', 'gallery')->isNotEmpty()) {
            return $this->productImages
                ->where('section', 'gallery')
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values();
        }

        $sourceGalleryImages = collect(data_get($this->source_payload, 'images', []))
            ->filter(fn ($path) => is_string($path) && $path !== '');

        return collect([$this->image_url])
            ->merge($sourceGalleryImages)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function descriptionImagePaths(): Collection
    {
        if ($this->relationLoaded('productImages') && $this->productImages->where('section', 'description')->isNotEmpty()) {
            return $this->productImages
                ->where('section', 'description')
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values();
        }

        return collect(data_get($this->source_payload, 'description_images', []))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();
    }
}
