<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionFeedback extends Model
{
    use HasFactory;

    protected $table = 'action_feedback';

    protected $fillable = [
        'user_id',
        'recommendation_id',
        'feedback',
        'body_note',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(DailyActionRecommendation::class, 'recommendation_id');
    }
}
