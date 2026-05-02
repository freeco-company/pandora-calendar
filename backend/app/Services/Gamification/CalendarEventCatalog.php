<?php

namespace App\Services\Gamification;

/**
 * 月曆送進集團 ADR-009 catalog 的事件型別清單（必須與 py-service `app/gamification/catalog.py`
 * `EVENT_CATALOG` 同步）。
 *
 * Naming：對齊集團其他 App 的 `<source_app>.<event>` 慣例（`meal.*` / `jerosse.*` / `skin.*`），
 * 月曆用 `calendar.*` prefix。
 *
 * py-service 端 XP（catalog.py 為準）：
 *   first_cycle               → 30 XP, milestone, lifetime_unique
 *   cycle_logged              → 5 XP, micro, daily_cap=5
 *   symptom_logged            → 3 XP, micro, daily_cap=9
 *   dodo_checkin              → 3 XP, micro, daily_cap=3
 *   track_7_days              → 30 XP, milestone
 *   streak_30_days            → 100 XP, major
 *   cycle_streak_3_months     → 200 XP, major
 *   pms_pattern_detected      → 50 XP, milestone
 *   pregnancy_logged          → 100 XP, major, lifetime_unique
 */
final class CalendarEventCatalog
{
    public const FIRST_CYCLE = 'calendar.first_cycle';
    public const CYCLE_LOGGED = 'calendar.cycle_logged';
    public const SYMPTOM_LOGGED = 'calendar.symptom_logged';
    public const MOOD_LOGGED = 'calendar.mood_logged';
    public const APP_OPENED = 'calendar.app_opened';
    public const DODO_CHECKIN = 'calendar.dodo_checkin';
    public const TRACK_7_DAYS = 'calendar.track_7_days';
    public const FULL_CYCLE_TRACKED = 'calendar.full_cycle_tracked';
    public const INSIGHT_READ = 'calendar.insight_read';
    public const STREAK_30_DAYS = 'calendar.streak_30_days';
    public const CYCLE_STREAK_3_MONTHS = 'calendar.cycle_streak_3_months';
    public const PMS_PATTERN_DETECTED = 'calendar.pms_pattern_detected';
    public const PREGNANCY_LOGGED = 'calendar.pregnancy_logged';

    public const ALL = [
        self::FIRST_CYCLE, self::CYCLE_LOGGED, self::SYMPTOM_LOGGED,
        self::MOOD_LOGGED, self::APP_OPENED, self::DODO_CHECKIN,
        self::TRACK_7_DAYS, self::FULL_CYCLE_TRACKED, self::INSIGHT_READ,
        self::STREAK_30_DAYS, self::CYCLE_STREAK_3_MONTHS,
        self::PMS_PATTERN_DETECTED, self::PREGNANCY_LOGGED,
    ];
}
