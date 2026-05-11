<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_request_id',
        'procurement_list_item_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'category_name',
        'image_url',
        'variant_sku_id',
        'variant_label',
        'variant_options',
        'quantity',
        'unit_price',
        'line_total',
        'moq',
        'product_snapshot',
    ];

    protected $casts = [
        'variant_options' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'moq' => 'integer',
        'product_snapshot' => 'array',
    ];

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
