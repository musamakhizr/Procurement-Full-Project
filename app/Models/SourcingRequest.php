<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'status',
        'title',
        'details',
        'quantity',
        'budget_text',
        'delivery_date',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(SourcingRequestLink::class);
    }

    public static function generateReference(): string
    {
        $nextNumber = (self::query()->max('id') ?? 0) + 1;

        return 'REQ-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'submitted' => 'Submitted',
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
                'under_review' => 'Under Review',
                'needs_info' => 'Needs Info',
                'quoted' => 'Quote Received',
                'approved' => 'Awaiting Approval',
                'processing' => 'Processing',
                default => ucfirst(str_replace('_', ' ', $this->status)),
            },
        );
    }

    protected function nextStep(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'accepted' => 'Our team accepted this request and will continue with the next procurement steps.',
                'rejected' => 'This request was declined. Please submit a revised request if needed.',
                'needs_info' => 'Provide the missing details so our team can continue.',
                'quoted' => 'Review the quote and approve the preferred option.',
                'approved' => 'Approve the order so fulfillment can begin.',
                default => 'Our procurement team is reviewing your request.',
            },
        );
    }

    protected function actionLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'accepted' => 'View Accepted Request',
                'rejected' => 'View Rejected Request',
                'needs_info' => 'Complete Details',
                'quoted' => 'Review Quote',
                'approved' => 'Approve Order',
                default => 'View Request',
            },
        );
    }
}
