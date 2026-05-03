<?php

namespace App\Services\Gamification;

use App\Models\UserSkillPath;
use Carbon\CarbonImmutable;

/**
 * Wave 13 — SkillPath service。
 *
 * 紅線：每月最多切 1 次（防刷 quest）。
 * 切換 cooldown 由 config('skill-paths.switch_cooldown_days') 控制。
 */
final class SkillPathService
{
    public const PATH_FERTILITY = 'fertility';

    public const PATH_WELLNESS = 'wellness';

    public const PATH_BEAUTY = 'beauty';

    public const VALID_PATHS = [self::PATH_FERTILITY, self::PATH_WELLNESS, self::PATH_BEAUTY];

    public function current(int $userId): ?UserSkillPath
    {
        return UserSkillPath::where('user_id', $userId)->first();
    }

    public function choose(int $userId, string $path): UserSkillPath
    {
        if (! in_array($path, self::VALID_PATHS, true)) {
            throw new \InvalidArgumentException("invalid skill path: {$path}");
        }

        $existing = $this->current($userId);
        $now = CarbonImmutable::now();

        if ($existing) {
            if ($existing->path === $path) {
                return $existing;
            }
            $cooldownDays = (int) config('skill-paths.switch_cooldown_days', 30);
            $lastChange = $existing->last_changed_at ?? $existing->chosen_at;
            if ($lastChange && $lastChange->copy()->addDays($cooldownDays)->isFuture()) {
                throw new \DomainException("skill_path_cooldown_active until {$lastChange->copy()->addDays($cooldownDays)->toIso8601String()}");
            }
            $existing->path = $path;
            $existing->last_changed_at = $now;
            $existing->save();

            return $existing->fresh();
        }

        return UserSkillPath::create([
            'user_id' => $userId,
            'path' => $path,
            'chosen_at' => $now,
            'last_changed_at' => null,
            'progress_json' => [],
        ]);
    }

    public function preferredActionTypes(int $userId): array
    {
        $current = $this->current($userId);
        if ($current === null) {
            return [];
        }
        $config = (array) config("skill-paths.paths.{$current->path}.preferred_action_types", []);

        return $config;
    }

    public function recommenderWeight(): float
    {
        return (float) config('skill-paths.recommender_weight', 0.2);
    }

    public function quests(int $userId): array
    {
        $current = $this->current($userId);
        if ($current === null) {
            return [];
        }
        $defs = (array) config("skill-paths.paths.{$current->path}.quests", []);
        $progress = (array) ($current->progress_json ?? []);
        $out = [];
        foreach ($defs as $q) {
            $key = $q['key'];
            $out[] = array_merge($q, [
                'completed' => (bool) ($progress[$key]['completed'] ?? false),
                'completed_at' => $progress[$key]['completed_at'] ?? null,
            ]);
        }

        return $out;
    }

    /**
     * Mark a quest completed (idempotent).
     */
    public function markQuestCompleted(int $userId, string $questKey): bool
    {
        $current = $this->current($userId);
        if ($current === null) {
            return false;
        }
        $progress = (array) ($current->progress_json ?? []);
        if (($progress[$questKey]['completed'] ?? false) === true) {
            return false;
        }
        $progress[$questKey] = [
            'completed' => true,
            'completed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        $current->progress_json = $progress;
        $current->save();

        return true;
    }
}
