<?php

namespace App\Services\BodyRhythm;

use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Calendar\BodyRhythm;

/**
 * 月曆是集團 GroupUserProfile.bodyRhythm 的**唯一寫入者**。
 *
 * 寫入時機：用戶記錄 / 編輯經期 → recompute → publish 到 Pandora Core，
 * Core 再對外發 webhook 給 meal / 肌膚 / 學院。
 *
 * Phase 0：寫 outbox，noop（Pandora Core 還沒接通）
 * P3+：worker flush outbox → POST Pandora Core /internal/group_user_profile/{uuid}/body_rhythm
 */
class BodyRhythmSyncService
{
    public function publish(User $user, BodyRhythm $rhythm): void
    {
        if (! $user->identity_uuid) {
            return; // pre-P1 user without identity, skip
        }

        OutboxEvent::create([
            'aggregate_type' => 'user',
            'aggregate_id' => $user->id,
            'event_kind' => 'group_user_profile.body_rhythm.updated',
            'destination' => OutboxEvent::DEST_BODY_RHYTHM,
            'payload' => [
                'user_uuid' => $user->identity_uuid,
                'source_app' => config('pandora.gamification.app_id', 'calendar'),
                'schema_version' => 1,
                'body_rhythm' => $rhythm->toArray(),
                'updated_at' => now()->toAtomString(),
            ],
            'occurred_at' => now(),
        ]);
    }
}
