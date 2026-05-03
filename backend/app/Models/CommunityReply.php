<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_id', 'user_id', 'anonymous_handle', 'body',
    'status', 'moderation_score',
    'like_count', 'reported_count', 'is_dodo',
])]
#[Hidden(['user_id'])]
class CommunityReply extends Model
{
    protected $table = 'community_replies';

    protected function casts(): array
    {
        return [
            'moderation_score' => 'float',
            'is_dodo' => 'boolean',
            'like_count' => 'integer',
            'reported_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
