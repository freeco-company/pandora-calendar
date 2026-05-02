<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory;

    protected $fillable = ['subscription_id', 'event_type', 'payload', 'occurred_at'];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
