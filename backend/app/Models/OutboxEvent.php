<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'aggregate_type', 'aggregate_id', 'event_kind', 'destination',
        'payload', 'occurred_at', 'published_at', 'attempts', 'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public const DEST_GAMIFICATION = 'gamification';
    public const DEST_CONVERSION = 'conversion';
    public const DEST_BODY_RHYTHM = 'body_rhythm';

    public function scopePending($q)
    {
        return $q->whereNull('published_at')->where('attempts', '<', 5);
    }
}
