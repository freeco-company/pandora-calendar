<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPauseRequest extends Model
{
    protected $fillable = [
        'user_id', 'reason', 'pause_months',
        'granted_discount_percent', 'granted_pause_until',
    ];

    protected $casts = [
        'granted_pause_until' => 'date:Y-m-d',
        'pause_months' => 'integer',
        'granted_discount_percent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
