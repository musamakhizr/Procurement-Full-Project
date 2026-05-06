<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'source_sku_id',
        'source_properties_key',
        'source_properties_name',
        'label',
        'option_values',
        'image_url',
        'source_image_url',
        'stock_quantity',
        'price',
        'original_price',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'option_values' => 'array',
        'stock_quantity' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
