<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthSample extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'source', 'metric', 'value', 'recorded_on', 'recorded_at', 'meta'];

    protected $casts = [
        'recorded_on' => 'date:Y-m-d',
        'recorded_at' => 'datetime',
        'value' => 'float',
        'meta' => 'array',
    ];

    public const METRIC_BASAL_TEMP = 'basal_temp';
    public const METRIC_SLEEP_HOURS = 'sleep_hours';
    public const METRIC_STEPS = 'steps';
    public const METRIC_WEIGHT_KG = 'weight_kg';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
