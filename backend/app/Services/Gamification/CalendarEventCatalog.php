<?php

namespace App\Services\Gamification;

/**
 * 月曆送進集團 ADR-009 catalog 的事件型別清單（與 py-service `app/gamification/catalog.py`
 * 同步維護）。
 *
 * py-service 端對應 XP value（建議值，最終以 catalog.py 為準）：
 *   first_cycle             → 30 XP
 *   cycle_logged            → 5 XP
 *   symptom_logged          → 2 XP
 *   dodo_checkin            → 3 XP
 *   streak_7_days           → 30 XP
 *   streak_30_days          → 100 XP
 *   cycle_streak_3_months   → 200 XP（月曆對集團 unique 的 milestone）
 *   pms_pattern_detected    → 50 XP
 *   pregnancy_logged        → 100 XP
 */
final class CalendarEventCatalog
{
    public const FIRST_CYCLE = 'pandora_calendar.first_cycle';
    public const CYCLE_LOGGED = 'pandora_calendar.cycle_logged';
    public const SYMPTOM_LOGGED = 'pandora_calendar.symptom_logged';
    public const DODO_CHECKIN = 'pandora_calendar.dodo_checkin';
    public const STREAK_7_DAYS = 'pandora_calendar.streak_7_days';
    public const STREAK_30_DAYS = 'pandora_calendar.streak_30_days';
    public const CYCLE_STREAK_3_MONTHS = 'pandora_calendar.cycle_streak_3_months';
    public const PMS_PATTERN_DETECTED = 'pandora_calendar.pms_pattern_detected';
    public const PREGNANCY_LOGGED = 'pandora_calendar.pregnancy_logged';

    public const ALL = [
        self::FIRST_CYCLE, self::CYCLE_LOGGED, self::SYMPTOM_LOGGED,
        self::DODO_CHECKIN, self::STREAK_7_DAYS, self::STREAK_30_DAYS,
        self::CYCLE_STREAK_3_MONTHS, self::PMS_PATTERN_DETECTED, self::PREGNANCY_LOGGED,
    ];
}
