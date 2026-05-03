<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'target_type', 'target_id', 'action',
    'reason', 'matched_rules', 'moderator_user_id',
])]
class CommunityModerationLog extends Model
{
    protected $table = 'community_moderation_logs';

    protected function casts(): array
    {
        return [
            'matched_rules' => 'array',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_user_id');
    }
}
