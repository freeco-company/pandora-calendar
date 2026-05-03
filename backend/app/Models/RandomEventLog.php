<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RandomEventLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'triggered_at';

    protected $fillable = [
        'user_id', 'event_key', 'triggered_on', 'triggered_at',
        'reward_coins', 'reward_xp', 'claimed', 'claimed_at',
    ];

    protected $casts = [
        'triggered_on' => 'date',
        'triggered_at' => 'datetime',
        'claimed' => 'boolean',
        'claimed_at' => 'datetime',
        'reward_coins' => 'integer',
        'reward_xp' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
