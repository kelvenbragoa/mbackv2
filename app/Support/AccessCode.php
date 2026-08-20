<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AccessCode
{
    /**
     * Alphabet without 0/O/1/I so codes are easy to read and type.
     */
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function random(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public static function ticketNumber(): string
    {
        return 'MTK-'.self::random(6);
    }

    public static function inviteNumber(): string
    {
        return 'INV-'.self::random(6);
    }

    public static function qrcode(): string
    {
        return self::random(10);
    }

    public static function uniqueTicketNumber(): string
    {
        return self::unique(
            fn (string $code) => DB::table('sell_details')->where('ticket_number', $code)->exists(),
            fn () => self::ticketNumber(),
        );
    }

    public static function uniqueTicketQrcode(): string
    {
        return self::unique(
            fn (string $code) => DB::table('sell_details')->where('qrcode', $code)->exists(),
            fn () => self::qrcode(),
        );
    }

    public static function uniqueInviteNumber(): string
    {
        return self::unique(
            fn (string $code) => DB::table('customer_invites')->where('invite_number', $code)->exists(),
            fn () => self::inviteNumber(),
        );
    }

    public static function uniqueInviteQrcode(): string
    {
        return self::unique(
            fn (string $code) => DB::table('customer_invites')->where('qrcode', $code)->exists(),
            fn () => self::qrcode(),
        );
    }

    public static function normalize(string $code): string
    {
        $code = strtoupper(preg_replace('/\s+/', '', $code) ?? '');

        if (preg_match('/^MTK([2-9A-HJ-NP-Z]{6})$/', $code, $matches)) {
            return 'MTK-'.$matches[1];
        }

        if (preg_match('/^INV([2-9A-HJ-NP-Z]{6})$/', $code, $matches)) {
            return 'INV-'.$matches[1];
        }

        return $code;
    }

    /**
     * @param  callable(string): bool  $exists
     * @param  callable(): string  $generate
     */
    private static function unique(callable $exists, callable $generate, int $attempts = 12): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $code = $generate();
            if (! $exists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar um código único.');
    }
}
