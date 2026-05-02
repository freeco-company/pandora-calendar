<?php

namespace App\Services\Gamification;

use App\Models\User;

interface GamificationPublisher
{
    /**
     * Publish a gamification event to py-service catalog (ADR-009).
     *
     * @param  string  $eventKind  e.g. calendar.first_cycle
     * @param  array  $context  freeform extra fields (cycle_id, streak_count, etc.)
     */
    public function publish(User $user, string $eventKind, array $context = []): void;
}
