<?php

namespace App\Services\Identity;

/**
 * Display-only projection of Pandora Core user.
 *
 * 注意：這個 DTO **不應該** 被 persistent。本機 cache 限制在 user.identity_uuid + display_name
 * （參考 ADR-007 §2.3）。
 */
final class IdentityProfile
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $displayName,
        public readonly ?string $avatarUrl = null,
        public readonly array $scopes = [],
        public readonly bool $linkedToMother = false,
        public readonly bool $hasMotherPurchase = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            displayName: $data['display_name'] ?? '朋友',
            avatarUrl: $data['avatar_url'] ?? null,
            scopes: $data['scopes'] ?? [],
            linkedToMother: $data['linked_to_mother'] ?? false,
            hasMotherPurchase: $data['has_mother_purchase'] ?? false,
        );
    }
}
