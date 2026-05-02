<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleSymptom extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'logged_on', 'tags', 'mood', 'basal_temperature', 'note'];

    protected $casts = [
        'logged_on' => 'date:Y-m-d',
        'tags' => 'array',
        'basal_temperature' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
