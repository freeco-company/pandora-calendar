<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DodoCheckin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'checked_on',
        'mood',
        'phase_at_checkin',
        'cycle_day_at_checkin',
        'dodo_response',
    ];

    protected $casts = [
        'checked_on' => 'date:Y-m-d',
        'cycle_day_at_checkin' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
