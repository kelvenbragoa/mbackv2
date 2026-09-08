<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class TicketFile
{
    public const TTL_MINUTES = 30;

    public static function path(int $sellId): string
    {
        return storage_path('app/tickets/ticket-'.$sellId.'.pdf');
    }

    public static function legacyPath(int $sellId): string
    {
        return storage_path('app/public/tickets/ticket-'.$sellId.'.pdf');
    }

    public static function put(int $sellId, string $binary): string
    {
        $dir = storage_path('app/tickets');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $path = self::path($sellId);
        file_put_contents($path, $binary);

        $legacy = self::legacyPath($sellId);
        if (is_file($legacy)) {
            @unlink($legacy);
        }

        return $path;
    }

    public static function ensure(int $sellId, ?string $binary = null): bool
    {
        if (is_string($binary) && $binary !== '' && str_starts_with($binary, '%PDF')) {
            self::put($sellId, $binary);

            return true;
        }

        if (is_file(self::path($sellId))) {
            return true;
        }

        $legacy = self::legacyPath($sellId);
        if (is_file($legacy)) {
            self::put($sellId, (string) file_get_contents($legacy));

            return true;
        }

        $pdf = TicketPdf::forSellId($sellId);
        if (! $pdf) {
            return false;
        }

        self::put($sellId, $pdf->output());

        return true;
    }

    public static function sellIdFromToken(string $token): ?int
    {
        $sellId = Cache::get('ticket-dl:'.$token);

        return $sellId ? (int) $sellId : null;
    }

    public static function temporaryUrl(int $sellId, ?string $binary = null): ?string
    {
        if (! self::ensure($sellId, $binary)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        Cache::put('ticket-dl:'.$token, $sellId, now()->addMinutes(self::TTL_MINUTES));

        return rtrim((string) config('app.url'), '/').'/api/ticket-files/'.$token.'/ticket.pdf';
    }
}
