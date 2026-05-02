<?php

namespace App\Services\Conversion;

use App\Models\User;

interface ConversionPublisher
{
    public function publish(User $user, string $eventKind, array $context = []): void;
}
