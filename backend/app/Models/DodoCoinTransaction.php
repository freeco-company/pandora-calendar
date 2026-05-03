<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DodoCoinTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'delta', 'source', 'metadata', 'balance_after',
    ];

    protected $casts = [
        'delta' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
