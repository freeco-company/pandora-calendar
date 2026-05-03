<?php

namespace App\Services\Admin;

use App\Models\CommunityModerationLog;

/**
 * Centralised writer for community_moderation_logs from the admin panel.
 *
 * Why a dedicated service: ADR-009 / community policy requires every manual
 * status change to leave an audit trail (who / when / which target / reason).
 * Inlining `CommunityModerationLog::create(...)` in every Filament action
 * makes the contract drift; this class is the single chokepoint.
 */
class ModerationActionLogger
{
    /**
     * @param  string  $targetType  e.g. 'community_post' / 'community_reply'
     * @param  string  $action      'auto_block' | 'flag' | 'approve' | 'remove' | 'hide' | 'warn'
     * @param  array<string,mixed>|null  $matchedRules
     */
    public static function log(
        string $targetType,
        int $targetId,
        string $action,
        ?int $moderatorUserId,
        ?string $reason = null,
        ?array $matchedRules = null,
    ): CommunityModerationLog {
        return CommunityModerationLog::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'moderator_user_id' => $moderatorUserId,
            'reason' => $reason,
            'matched_rules' => $matchedRules,
        ]);
    }
}
