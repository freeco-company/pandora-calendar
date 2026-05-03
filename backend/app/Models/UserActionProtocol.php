<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActionProtocol extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phase',
        'action_key',
        'sample_size',
        'effectiveness_score',
        'last_calculated_at',
    ];

    protected $casts = [
        'sample_size' => 'integer',
        'effectiveness_score' => 'float',
        'last_calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
