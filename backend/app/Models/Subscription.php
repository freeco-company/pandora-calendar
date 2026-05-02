<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'platform', 'product_id', 'original_transaction_id',
        'latest_receipt_hash', 'starts_at', 'ends_at', 'renewed_at',
        'cancelled_at', 'auto_renew', 'status', 'raw_payload',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'renewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'raw_payload' => 'array',
    ];

    public const PLATFORMS = ['apple', 'google', 'ecpay'];
    public const PRODUCT_MONTHLY = 'calendar.premium.monthly';
    public const PRODUCT_ANNUAL = 'calendar.premium.annual';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'grace'], true)
            && (! $this->ends_at || $this->ends_at->isFuture());
    }
}
