<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePatternReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cycle_id',
        'phase_summary',
        'top_actions',
        'vs_previous',
        'dodo_message',
        'generated_at',
    ];

    protected $casts = [
        'phase_summary' => 'array',
        'top_actions' => 'array',
        'vs_previous' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
