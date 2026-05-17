<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceShopImport extends Model
{
    protected $fillable = [
        'admin_user_id',
        'seed_url',
        'seed_platform',
        'seed_num_iid',
        'seller_id',
        'shop_id',
        'status',
        'total_product_links',
        'imported_product_links',
        'error',
        'product_links',
        'raw_seed_payload',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'product_links' => 'array',
        'raw_seed_payload' => 'array',
        'metadata' => 'array',
        'total_product_links' => 'integer',
        'imported_product_links' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
