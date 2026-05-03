<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolInsightDismissed extends Model
{
    protected $table = 'protocol_insight_dismissed';

    protected $fillable = ['user_id', 'insight_key', 'dismissed_at'];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
