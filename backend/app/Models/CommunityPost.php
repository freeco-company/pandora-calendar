<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'anonymous_handle', 'category', 'title', 'body',
    'status', 'moderation_score', 'published_at',
    'like_count', 'reply_count', 'reported_count',
])]
// user_id is hidden from API serialization — anonymity invariant.
#[Hidden(['user_id'])]
class CommunityPost extends Model
{
    protected $table = 'community_posts';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'moderation_score' => 'float',
            'like_count' => 'integer',
            'reply_count' => 'integer',
            'reported_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CommunityReply::class, 'post_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }
}
