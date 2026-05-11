<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuoteRequest extends Model
{
    use HasFactory;

    public const ADMIN_MUTABLE_STATUSES = ['accepted', 'rejected'];

    protected $fillable = [
        'user_id',
        'reference',
        'status',
        'total_items',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_items' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteRequestItem::class);
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'QUOTE-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'submitted' => 'Submitted',
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
                default => ucfirst(str_replace('_', ' ', (string) $this->status)),
            },
        );
    }
}
