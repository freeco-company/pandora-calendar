<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolarTermParticipation extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'completed_at';

    protected $fillable = [
        'user_id', 'term_key', 'year', 'earned_coins', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'earned_coins' => 'integer',
        'year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
