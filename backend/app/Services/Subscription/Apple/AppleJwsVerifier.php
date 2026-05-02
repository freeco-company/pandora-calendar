<?php

namespace App\Services\Subscription\Apple;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 驗證 Apple App Store Server Notification V2 / StoreKit 2 transaction signedPayload。
 *
 * 演算法：JWS (RFC 7515) using ES256.
 *
 * Header 內含 `x5c` array of base64-DER X.509 certs：
 *   [0] leaf cert (issued by Apple WWDR for ASN signing)
 *   [1] intermediate Apple WWDR cert
 *   [2] Apple Root CA - G3 (or chain to it)
 *
 * Verification:
 *   1. Verify chain: leaf signed by intermediate, intermediate signed by root
 *   2. Verify root cert matches AppleRootCAG3 fingerprint
 *   3. Verify expiration on each cert
 *   4. Use leaf public key to verify JWS signature (ES256)
 *
 * Apple Root CA - G3 SHA-256 fingerprint:
 *   63 34 3A BF B8 9A 6A 03 EE B8 8E 92 1E 5C 22 D7
 *   76 67 53 16 D9 96 7B 1F 0F EA 9D 8F C7 1F 2F C3
 *
 * Spec：
 *   - https://developer.apple.com/documentation/appstoreservernotifications/signedpayload
 *   - https://www.apple.com/certificateauthority/AppleRootCA-G3.cer
 */
class AppleJwsVerifier
{
    public const APPLE_ROOT_CA_G3_SHA256 = '63343abfb89a6a03eeb88e921e5c22d776675316d9967b1f0fea9d8fc71f2fc3';

    /** @return array decoded payload */
    public function verifyAndDecode(string $signedPayload): array
    {
        $parts = explode('.', $signedPayload);
        if (count($parts) !== 3) {
            throw new AppleJwsVerifyException('signedPayload is not a valid JWS (need 3 segments)');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = $this->jsonDecode($this->base64UrlDecode($headerB64));
        $payload = $this->jsonDecode($this->base64UrlDecode($payloadB64));
        $signature = $this->base64UrlDecode($signatureB64);

        if (($header['alg'] ?? '') !== 'ES256') {
            throw new AppleJwsVerifyException('unexpected alg: '.($header['alg'] ?? '<missing>'));
        }
        if (empty($header['x5c']) || ! is_array($header['x5c'])) {
            throw new AppleJwsVerifyException('missing x5c header');
        }

        $certPems = array_map([$this, 'derToPem'], $header['x5c']);

        // 1. Verify chain
        $this->verifyCertChain($certPems);

        // 2. Verify root anchors to Apple Root CA G3
        $rootSha = strtolower(openssl_x509_fingerprint(end($certPems), 'sha256'));
        if (! $this->isAcceptedRoot($rootSha)) {
            throw new AppleJwsVerifyException("root cert not Apple Root CA G3 (sha256=$rootSha)");
        }

        // 3. Expiration of leaf
        $leafInfo = openssl_x509_parse($certPems[0]);
        if (! $leafInfo || ($leafInfo['validTo_time_t'] ?? 0) < time()) {
            throw new AppleJwsVerifyException('leaf cert expired or unparseable');
        }

        // 4. Verify JWS signature with leaf public key (ES256 → DER ASN.1 conversion)
        $signingInput = "$headerB64.$payloadB64";
        $derSignature = $this->joseSignatureToDer($signature);

        $publicKey = openssl_pkey_get_public($certPems[0]);
        if ($publicKey === false) {
            throw new AppleJwsVerifyException('cannot extract leaf public key');
        }

        $ok = openssl_verify($signingInput, $derSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new AppleJwsVerifyException('JWS signature verification failed');
        }

        return $payload;
    }

    /**
     * Apple sometimes nests further signed JWTs inside the payload (signedRenewalInfo,
     * signedTransactionInfo). Decode them recursively, verifying each.
     */
    public function decodeNestedTransaction(array $payload): array
    {
        $data = $payload['data'] ?? [];
        if (! empty($data['signedTransactionInfo'])) {
            $data['transactionInfo'] = $this->verifyAndDecode($data['signedTransactionInfo']);
        }
        if (! empty($data['signedRenewalInfo'])) {
            $data['renewalInfo'] = $this->verifyAndDecode($data['signedRenewalInfo']);
        }
        $payload['data'] = $data;

        return $payload;
    }

    private function verifyCertChain(array $certPems): void
    {
        for ($i = 0; $i < count($certPems) - 1; $i++) {
            $childRes = openssl_x509_read($certPems[$i]);
            $issuerRes = openssl_x509_read($certPems[$i + 1]);
            if ($childRes === false || $issuerRes === false) {
                throw new AppleJwsVerifyException("cert at index $i unreadable");
            }

            // Best-effort: PHP's openssl_x509_verify returns 1 on success.
            $ok = @openssl_x509_verify($childRes, openssl_pkey_get_public($issuerRes));
            if ($ok !== 1) {
                throw new AppleJwsVerifyException("cert chain link broken at index $i");
            }
        }
    }

    private function isAcceptedRoot(string $sha256): bool
    {
        // Allow override via config for sandbox / future Apple root rotation.
        $accepted = (array) config('pandora.subscription.apple_accepted_roots', [self::APPLE_ROOT_CA_G3_SHA256]);

        return in_array($sha256, array_map('strtolower', $accepted), true);
    }

    private function derToPem(string $b64Der): string
    {
        $der = base64_decode($b64Der);

        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
    }

    private function base64UrlDecode(string $s): string
    {
        $padded = str_pad(strtr($s, '-_', '+/'), strlen($s) + (4 - strlen($s) % 4) % 4, '=');

        return base64_decode($padded);
    }

    private function jsonDecode(string $s): array
    {
        $data = json_decode($s, true);
        if (! is_array($data)) {
            throw new AppleJwsVerifyException('invalid JSON in JWS segment');
        }

        return $data;
    }

    /**
     * JOSE ES256 signatures are 64 bytes (R||S concat).
     * openssl_verify expects ASN.1 DER (SEQUENCE of two INTEGERs).
     */
    private function joseSignatureToDer(string $sig): string
    {
        if (strlen($sig) !== 64) {
            throw new AppleJwsVerifyException('ES256 signature must be 64 bytes, got '.strlen($sig));
        }
        $r = substr($sig, 0, 32);
        $s = substr($sig, 32, 32);

        $rBytes = $this->trimToInteger($r);
        $sBytes = $this->trimToInteger($s);

        $rDer = "\x02".chr(strlen($rBytes)).$rBytes;
        $sDer = "\x02".chr(strlen($sBytes)).$sBytes;

        $body = $rDer.$sDer;

        return "\x30".chr(strlen($body)).$body;
    }

    private function trimToInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            return "\x00";
        }
        // ASN.1 INTEGER must not have a high bit set (would indicate negative);
        // prepend 0x00 if necessary.
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return $bytes;
    }
}
