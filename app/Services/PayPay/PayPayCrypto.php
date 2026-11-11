<?php

declare(strict_types=1);

namespace App\Services\PayPay;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Framework-free RSA helpers for PayPay AO's documented scheme:
 *  - biz_content: JSON -> RSA "encrypt" with the PRIVATE key (PKCS#1 v1.5, block = keyBytes - 11) -> base64
 *  - sign: SHA1withRSA over the sorted "k=v&k=v" string -> base64
 *  - verification of notifications uses PayPay's PUBLIC key
 */
final class PayPayCrypto
{
    /** Accepts a PEM (PKCS#8 or PKCS#1) or a bare base64 body (PKCS#8, as in the docs' Java samples). */
    public static function loadPrivateKey(string $key): OpenSSLAsymmetricKey
    {
        $key = self::normalize($key);

        if (str_contains($key, '-----BEGIN')) {
            $res = openssl_pkey_get_private($key);
            if ($res === false) {
                throw new RuntimeException('PayPay: invalid private key');
            }
            return $res;
        }

        $body = chunk_split((string) preg_replace('/\s+/', '', $key), 64, "\n");
        foreach (['PRIVATE KEY', 'RSA PRIVATE KEY'] as $label) {
            $res = openssl_pkey_get_private("-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n");
            if ($res !== false) {
                return $res;
            }
        }
        throw new RuntimeException('PayPay: invalid private key');
    }

    /** Accepts a PEM or a bare base64 SubjectPublicKeyInfo body. */
    public static function loadPublicKey(string $key): OpenSSLAsymmetricKey
    {
        $key = self::normalize($key);

        if (str_contains($key, '-----BEGIN')) {
            $res = openssl_pkey_get_public($key);
            if ($res === false) {
                throw new RuntimeException('PayPay: invalid public key');
            }
            return $res;
        }

        $body = chunk_split((string) preg_replace('/\s+/', '', $key), 64, "\n");
        foreach (['PUBLIC KEY', 'RSA PUBLIC KEY'] as $label) {
            $res = openssl_pkey_get_public("-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n");
            if ($res !== false) {
                return $res;
            }
        }
        throw new RuntimeException('PayPay: invalid public key');
    }

    public static function encryptWithPrivateKey(string $plain, OpenSSLAsymmetricKey $privateKey): string
    {
        $block = intdiv(self::bits($privateKey), 8) - 11;
        $out = '';
        foreach (str_split($plain, $block) as $chunk) {
            if (!openssl_private_encrypt($chunk, $enc, $privateKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('PayPay: RSA encryption failed');
            }
            $out .= $enc;
        }
        return base64_encode($out);
    }

    /** Inverse of encryptWithPrivateKey; used by tests and the debugging tool. */
    public static function decryptWithPublicKey(string $base64, OpenSSLAsymmetricKey $publicKey): string
    {
        $raw = base64_decode($base64, true);
        if ($raw === false) {
            throw new RuntimeException('PayPay: invalid base64');
        }
        $block = intdiv(self::bits($publicKey), 8);
        $out = '';
        foreach (str_split($raw, $block) as $chunk) {
            if (!openssl_public_decrypt($chunk, $dec, $publicKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('PayPay: RSA decryption failed');
            }
            $out .= $dec;
        }
        return $out;
    }

    /**
     * "k1=v1&k2=v2" with keys sorted by ASCII, skipping empty values, `sign` and `sign_type`.
     * Values must be the raw (not URL-encoded) strings.
     *
     * @param array<string, mixed> $params
     */
    public static function signingString(array $params): string
    {
        $clean = [];
        foreach ($params as $k => $v) {
            $k = (string) $k;
            if ($v === null || $v === '' || is_array($v)) {
                continue;
            }
            if (strcasecmp($k, 'sign') === 0 || strcasecmp($k, 'sign_type') === 0) {
                continue;
            }
            $clean[$k] = (string) $v;
        }
        ksort($clean, SORT_STRING);

        $parts = [];
        foreach ($clean as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }

    public static function sign(string $text, OpenSSLAsymmetricKey $privateKey): string
    {
        if (!openssl_sign($text, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('PayPay: signing failed');
        }
        return base64_encode($signature);
    }

    public static function verify(string $text, string $signatureBase64, OpenSSLAsymmetricKey $publicKey): bool
    {
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false || $signature === '') {
            return false;
        }
        return openssl_verify($text, $signature, $publicKey, OPENSSL_ALGO_SHA1) === 1;
    }

    private static function bits(OpenSSLAsymmetricKey $key): int
    {
        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['bits'])) {
            throw new RuntimeException('PayPay: cannot read key size');
        }
        return (int) $details['bits'];
    }

    private static function normalize(string $key): string
    {
        // allows keys stored in .env with literal "\n"
        return trim(str_replace('\n', "\n", $key));
    }
}
