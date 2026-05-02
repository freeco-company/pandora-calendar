<?php

namespace App\Services\Subscription;

final class GateResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $message = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
