<?php

namespace App\Services\Community;

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Community posting gate.
 *
 * Why a gate at all: anonymous Q&A boards attract spam / commercial / drive-by
 * accounts within hours of launch. Requiring "real engagement" (14 days +
 * actual cycle/symptom records) raises the cost of abuse without blocking
 * legitimate users — the median onboarded user hits 5 records well before day 14.
 *
 * Tiered thresholds (post > reply > report) follow the principle:
 * higher reach = higher proof-of-engagement.
 */
class CommunityGate
{
    public const POST_MIN_DAYS = 14;
    public const POST_MIN_RECORDS = 5;
    public const REPLY_MIN_DAYS = 7;
    public const REPORT_MIN_DAYS = 1;

    /**
     * @return array{ok: bool, reason?: string, hint?: string, days_remaining?: int, records_remaining?: int}
     */
    public function canPost(User $user): array
    {
        $days = $this->daysSinceJoined($user);
        if ($days < self::POST_MIN_DAYS) {
            return [
                'ok' => false,
                'reason' => 'not_yet_eligible',
                'hint' => '再記錄 '.(self::POST_MIN_DAYS - $days).' 天就可以發文，先用 App 認識妳的身體節律吧。',
                'days_remaining' => self::POST_MIN_DAYS - $days,
            ];
        }

        $records = $this->totalRecords($user);
        if ($records < self::POST_MIN_RECORDS) {
            return [
                'ok' => false,
                'reason' => 'not_enough_records',
                'hint' => '再記錄 '.(self::POST_MIN_RECORDS - $records).' 筆週期或症狀，社群朋友才能更懂妳的分享。',
                'records_remaining' => self::POST_MIN_RECORDS - $records,
            ];
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, reason?: string, hint?: string} */
    public function canReply(User $user): array
    {
        $days = $this->daysSinceJoined($user);
        if ($days < self::REPLY_MIN_DAYS) {
            return [
                'ok' => false,
                'reason' => 'not_yet_eligible',
                'hint' => '再記錄 '.(self::REPLY_MIN_DAYS - $days).' 天就可以回覆其他朋友。',
            ];
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, reason?: string, hint?: string} */
    public function canReport(User $user): array
    {
        $days = $this->daysSinceJoined($user);
        if ($days < self::REPORT_MIN_DAYS) {
            return [
                'ok' => false,
                'reason' => 'not_yet_eligible',
                'hint' => '帳號太新，無法檢舉。',
            ];
        }

        return ['ok' => true];
    }

    private function daysSinceJoined(User $user): int
    {
        $createdAt = $user->created_at
            ? CarbonImmutable::parse($user->created_at)
            : CarbonImmutable::now();

        return max(0, (int) $createdAt->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay()));
    }

    private function totalRecords(User $user): int
    {
        return Cycle::where('user_id', $user->id)->count()
            + CycleSymptom::where('user_id', $user->id)->count();
    }
}
