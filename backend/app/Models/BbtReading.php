<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BbtReading extends Model
{
    protected $fillable = ['user_id', 'measured_on', 'temperature_c', 'note'];

    protected $casts = [
        'measured_on' => 'date',
        'temperature_c' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
