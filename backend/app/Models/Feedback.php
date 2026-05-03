<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id', 'category', 'message', 'app_version', 'device_info',
    ];

    protected $casts = [
        'device_info' => 'array',
    ];

    public const CATEGORIES = ['bug', 'feature', 'content', 'other'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
