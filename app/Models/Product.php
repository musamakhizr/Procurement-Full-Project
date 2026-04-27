<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'image_url',
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
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
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
}
