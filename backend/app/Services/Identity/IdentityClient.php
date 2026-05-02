<?php

namespace App\Services\Identity;

/**
 * 對 Pandora Core Identity Service 的 client interface（ADR-007）。
 *
 * Phase 0-P1：用 MockIdentityClient（本機 SQLite 寄住 PII，僅 dev / testing 用）
 * P1 完成 / P2+：切換到 HttpIdentityClient（HTTP call Pandora Core）
 *
 * 切換點：bind in AppServiceProvider，依 IDENTITY_DRIVER env。
 */
interface IdentityClient
{
    public function lookupByUuid(string $uuid): ?IdentityProfile;

    public function lookupByEmail(string $email): ?IdentityProfile;

    public function exchangeAccessToken(string $accessToken): ?IdentityProfile;

    public function listConsentScopes(string $uuid): array;
}
