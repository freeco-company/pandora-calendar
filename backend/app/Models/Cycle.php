<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cycle extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'peak_flow', 'notes'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'peak_flow' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lengthInDays(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
