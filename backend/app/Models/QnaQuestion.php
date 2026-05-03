<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QnaQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'sources',
        'llm_provider',
        'response_time_ms',
        'safety_flag',
    ];

    protected $casts = [
        'sources' => 'array',
        'response_time_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
