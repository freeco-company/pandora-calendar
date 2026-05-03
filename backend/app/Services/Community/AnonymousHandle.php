<?php

namespace App\Services\Community;

/**
 * Anonymous handle generator.
 *
 * Per spec: same user gets the same handle within a single post / reply chain
 * (so readers can follow "OP replied below"), but DIFFERENT handles across
 * posts (so a poster can't be tracked across topics).
 *
 * Implementation: hash(user_id + per-post salt + global secret) → 12 char base32.
 * For a brand-new post, caller passes the post_id (or a draft random salt for
 * pre-insert preview); for a reply, caller passes the parent post_id so the
 * OP appears as the same handle on their own thread.
 */
class AnonymousHandle
{
    private const BASE32 = 'ABCDEFGHJKMNPQRSTVWXYZ23456789'; // no I, L, O, 0, 1 — readability

    public function __construct(
        private readonly string $secret,
    ) {}

    public function forPost(int $userId, int $postScopeId): string
    {
        return $this->derive($userId, 'post:'.$postScopeId);
    }

    private function derive(int $userId, string $scope): string
    {
        $raw = hash_hmac('sha256', $userId.'|'.$scope, $this->secret, true);
        $out = '';
        $alphabet = self::BASE32;
        $alphabetLen = strlen($alphabet);
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[ord($raw[$i]) % $alphabetLen];
        }

        return $out;
    }
}
