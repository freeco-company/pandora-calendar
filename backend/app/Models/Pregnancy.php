<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pregnancy extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'lmp_date', 'estimated_due_date', 'ended_on', 'outcome', 'milestones'];

    protected $casts = [
        'lmp_date' => 'date:Y-m-d',
        'estimated_due_date' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
        'milestones' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gestationalWeek(): int
    {
        return (int) floor($this->lmp_date->diffInDays(now()) / 7);
    }
}
