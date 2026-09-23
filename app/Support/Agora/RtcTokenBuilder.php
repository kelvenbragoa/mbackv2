<?php

namespace App\Support\Agora;

use RuntimeException;

/**
 * Agora AccessToken2 ("007") for the RTC service, compatible with Agora's official RtcTokenBuilder2.
 */
class RtcTokenBuilder
{
    public const ROLE_PUBLISHER = 1;
    public const ROLE_SUBSCRIBER = 2;

    private const VERSION = '007';
    private const SERVICE_RTC = 1;

    private const PRIVILEGE_JOIN_CHANNEL = 1;
    private const PRIVILEGE_PUBLISH_AUDIO = 2;
    private const PRIVILEGE_PUBLISH_VIDEO = 3;
    private const PRIVILEGE_PUBLISH_DATA = 4;

    /**
     * @param  int  $uid  0 lets the token be used with any uid
     * @param  int  $expire  seconds from now
     */
    public static function buildTokenWithUid(
        string $appId,
        string $appCertificate,
        string $channel,
        int $uid,
        int $role,
        int $expire,
        ?int $issueTs = null,
        ?int $salt = null
    ): string {
        if (! self::isHex32($appId) || ! self::isHex32($appCertificate)) {
            throw new RuntimeException('Credenciais Agora inválidas. Verifica AGORA_APP_ID e AGORA_APP_CERTIFICATE.');
        }

        $privileges = [self::PRIVILEGE_JOIN_CHANNEL => $expire];

        if ($role === self::ROLE_PUBLISHER) {
            $privileges[self::PRIVILEGE_PUBLISH_AUDIO] = $expire;
            $privileges[self::PRIVILEGE_PUBLISH_VIDEO] = $expire;
            $privileges[self::PRIVILEGE_PUBLISH_DATA] = $expire;
        }

        $issueTs ??= time();
        $salt ??= random_int(1, 99999999);

        $service = self::packUint16(self::SERVICE_RTC)
            .self::packMapUint32($privileges)
            .self::packString($channel)
            .self::packString($uid === 0 ? '' : (string) $uid);

        $data = self::packString($appId)
            .self::packUint32($issueTs)
            .self::packUint32($expire)
            .self::packUint32($salt)
            .self::packUint16(1)
            .$service;

        $signingKey = hash_hmac('sha256', $appCertificate, self::packUint32($issueTs), true);
        $signingKey = hash_hmac('sha256', $signingKey, self::packUint32($salt), true);
        $signature = hash_hmac('sha256', $data, $signingKey, true);

        return self::VERSION.base64_encode(zlib_encode(self::packString($signature).$data, ZLIB_ENCODING_DEFLATE));
    }

    private static function isHex32(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F]{32}$/', $value);
    }

    private static function packUint16(int $value): string
    {
        return pack('v', $value);
    }

    private static function packUint32(int $value): string
    {
        return pack('V', $value);
    }

    private static function packString(string $value): string
    {
        return self::packUint16(strlen($value)).$value;
    }

    private static function packMapUint32(array $map): string
    {
        ksort($map);
        $packed = self::packUint16(count($map));

        foreach ($map as $key => $value) {
            $packed .= self::packUint16($key).self::packUint32($value);
        }

        return $packed;
    }
}
