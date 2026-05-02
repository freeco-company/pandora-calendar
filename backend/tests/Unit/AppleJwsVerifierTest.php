<?php

use App\Services\Subscription\Apple\AppleJwsVerifier;
use App\Services\Subscription\Apple\AppleJwsVerifyException;

/**
 * 用測試用的 self-signed cert chain 模擬 Apple x5c 結構，verify ES256 signature 與
 * cert chain 邏輯。Production 真實 verify 會 reject (因為 root cert 不是 Apple Root G3)，
 * 所以這個測試用 config 把 root SHA-256 fingerprint allowlist 替換成測試 cert 的 fingerprint。
 */
beforeEach(function () {
    [$this->rootCertPem, $this->rootKey] = makeSelfSignedCa('Test Root CA');
    [$this->intCertPem, $this->intKey] = makeIntermediate($this->rootCertPem, $this->rootKey, 'Test Intermediate');
    [$this->leafCertPem, $this->leafKey] = makeLeafSigner($this->intCertPem, $this->intKey, 'Test Leaf');

    $rootSha = strtolower(openssl_x509_fingerprint($this->rootCertPem, 'sha256'));
    config(['pandora.subscription.apple_accepted_roots' => [$rootSha]]);
});

it('verifies a valid JWS with synthetic chain', function () {
    $payload = ['notificationType' => 'SUBSCRIBED', 'data' => ['signedTransactionInfo' => '...placeholder']];

    $jws = signJws($payload, $this->leafKey, [$this->leafCertPem, $this->intCertPem, $this->rootCertPem]);

    $verifier = new AppleJwsVerifier;
    $decoded = $verifier->verifyAndDecode($jws);

    expect($decoded['notificationType'])->toBe('SUBSCRIBED');
});

it('rejects when payload tampered after signing', function () {
    $payload = ['notificationType' => 'SUBSCRIBED'];
    $jws = signJws($payload, $this->leafKey, [$this->leafCertPem, $this->intCertPem, $this->rootCertPem]);

    // Tamper: replace the payload segment with a different one
    [$h, $p, $s] = explode('.', $jws);
    $tamperedPayload = base64UrlEncode(json_encode(['notificationType' => 'REFUND']));
    $tamperedJws = "$h.$tamperedPayload.$s";

    $verifier = new AppleJwsVerifier;

    expect(fn () => $verifier->verifyAndDecode($tamperedJws))
        ->toThrow(AppleJwsVerifyException::class);
});

it('rejects when root cert not in accepted list', function () {
    config(['pandora.subscription.apple_accepted_roots' => ['0000000000000000000000000000000000000000000000000000000000000000']]);

    $jws = signJws(['notificationType' => 'SUBSCRIBED'], $this->leafKey, [$this->leafCertPem, $this->intCertPem, $this->rootCertPem]);

    expect(fn () => (new AppleJwsVerifier)->verifyAndDecode($jws))
        ->toThrow(AppleJwsVerifyException::class, 'root cert not Apple Root CA G3');
});

it('rejects when alg is not ES256', function () {
    $jws = base64UrlEncode(json_encode(['alg' => 'HS256', 'x5c' => ['xxx']])).'.'.base64UrlEncode('{}').'.AAAA';

    expect(fn () => (new AppleJwsVerifier)->verifyAndDecode($jws))
        ->toThrow(AppleJwsVerifyException::class, 'unexpected alg');
});

it('rejects when x5c missing', function () {
    $jws = base64UrlEncode(json_encode(['alg' => 'ES256'])).'.'.base64UrlEncode('{}').'.AAAA';

    expect(fn () => (new AppleJwsVerifier)->verifyAndDecode($jws))
        ->toThrow(AppleJwsVerifyException::class, 'missing x5c header');
});

// ── helpers ──────────────────────────────────────────────────────────────────

function makeSelfSignedCa(string $cn): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $csr = openssl_csr_new(
        ['CN' => $cn, 'O' => 'Test'],
        $key,
        ['digest_alg' => 'sha256'],
    );
    $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256', 'x509_extensions' => 'v3_ca']);
    openssl_x509_export($cert, $pem);

    return [$pem, $key];
}

function makeIntermediate($rootPem, $rootKey, string $cn): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $csr = openssl_csr_new(
        ['CN' => $cn, 'O' => 'Test'],
        $key,
        ['digest_alg' => 'sha256'],
    );
    $cert = openssl_csr_sign($csr, $rootPem, $rootKey, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $pem);

    return [$pem, $key];
}

function makeLeafSigner($issuerPem, $issuerKey, string $cn): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $csr = openssl_csr_new(
        ['CN' => $cn, 'O' => 'Test'],
        $key,
        ['digest_alg' => 'sha256'],
    );
    $cert = openssl_csr_sign($csr, $issuerPem, $issuerKey, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $pem);

    return [$pem, $key];
}

function signJws(array $payload, $privKey, array $certPems): string
{
    $x5c = array_map(function ($pem) {
        $b64 = preg_replace('/-----[^-]+-----|\s+/', '', $pem);

        return $b64;
    }, $certPems);

    $header = ['alg' => 'ES256', 'typ' => 'JWT', 'x5c' => $x5c];
    $signingInput = base64UrlEncode(json_encode($header)).'.'.base64UrlEncode(json_encode($payload));

    $derSig = '';
    if (! openssl_sign($signingInput, $derSig, $privKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('cannot sign for test');
    }
    // openssl_sign produces DER for EC; convert to JOSE 64-byte raw R||S
    $jose = derToJoseSig($derSig);

    return "$signingInput.".base64UrlEncode($jose);
}

function derToJoseSig(string $der): string
{
    // Parse SEQUENCE (0x30 len SEQUENCE-body)
    if ($der[0] !== "\x30") {
        throw new RuntimeException('bad DER signature');
    }
    $offset = 2; // skip tag + length
    if ((ord($der[1]) & 0x80) !== 0) {
        $offset = 2 + (ord($der[1]) & 0x7F);
    }
    // INTEGER R
    if ($der[$offset] !== "\x02") {
        throw new RuntimeException('bad DER R');
    }
    $rLen = ord($der[$offset + 1]);
    $r = substr($der, $offset + 2, $rLen);
    $offset = $offset + 2 + $rLen;
    // INTEGER S
    if ($der[$offset] !== "\x02") {
        throw new RuntimeException('bad DER S');
    }
    $sLen = ord($der[$offset + 1]);
    $s = substr($der, $offset + 2, $sLen);

    // Pad to 32 bytes each
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r.$s;
}

function base64UrlEncode(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
