<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyActionRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recommended_on',
        'action_key',
        'phase',
        'cycle_day',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'recommended_on' => 'date:Y-m-d',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'cycle_day' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(ActionFeedback::class, 'recommendation_id');
    }
}
