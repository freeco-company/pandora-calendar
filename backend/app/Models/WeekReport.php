<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeekReport extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'week_start', 'summary', 'pdf_path', 'generated_at'];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
        'summary' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
