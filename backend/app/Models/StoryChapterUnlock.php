<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryChapterUnlock extends Model
{
    protected $fillable = [
        'user_id', 'chapter', 'unlock_source', 'unlocked_at', 'read_at',
    ];

    protected $casts = [
        'chapter' => 'integer',
        'unlocked_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
