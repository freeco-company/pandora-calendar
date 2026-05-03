<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyDexEntry extends Model
{
    protected $fillable = [
        'user_id', 'symptom_key', 'first_logged_on', 'log_count',
    ];

    protected $casts = [
        'first_logged_on' => 'date',
        'log_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
