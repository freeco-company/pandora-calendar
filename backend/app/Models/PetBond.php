<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetBond extends Model
{
    protected $fillable = [
        'user_id', 'pet_species', 'bond_xp',
        'feed_count_today', 'pet_head_count_today', 'counters_reset_on',
    ];

    protected $casts = [
        'bond_xp' => 'integer',
        'feed_count_today' => 'integer',
        'pet_head_count_today' => 'integer',
        'counters_reset_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
