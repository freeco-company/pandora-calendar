<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pregnancy extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';

    public const REASON_BIRTH = 'birth';
    public const REASON_MISCARRIAGE = 'miscarriage';
    public const REASON_CANCELLED = 'cancelled';
    public const REASON_FALSE_ALARM = 'false_alarm';

    protected $fillable = [
        'user_id',
        'lmp_date',
        'estimated_due_date',
        'ended_on',
        'outcome',
        'milestones',
        'status',
        'ended_reason',
        'mode_started_at',
    ];

    protected $casts = [
        'lmp_date' => 'date:Y-m-d',
        'estimated_due_date' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
        'mode_started_at' => 'datetime',
        'milestones' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gestationalWeek(): int
    {
        // diffInDays(now()) is sometimes float; floor + clamp 1..42 for safety
        $days = (int) floor(abs($this->lmp_date->diffInDays(now())));

        return max(1, min(42, (int) floor($days / 7) + 1));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ended_on === null;
    }
}
