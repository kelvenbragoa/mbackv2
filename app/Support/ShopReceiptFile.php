<?php

namespace App\Support;

use App\Models\SellShop;
use Illuminate\Support\Facades\Cache;

class ShopReceiptFile
{
    public const TTL_MINUTES = 30;

    public static function path(int $sellId): string
    {
        return storage_path('app/shop-receipts/receipt-'.$sellId.'.pdf');
    }

    public static function put(int $sellId, string $binary): string
    {
        $dir = storage_path('app/shop-receipts');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $path = self::path($sellId);
        file_put_contents($path, $binary);

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

        $sell = SellShop::find($sellId);
        if (! $sell) {
            return false;
        }

        $pdf = ShopOrderPdf::forSell($sell);
        if (! $pdf) {
            return false;
        }

        self::put($sellId, $pdf->output());

        return true;
    }

    public static function sellIdFromToken(string $token): ?int
    {
        $sellId = Cache::get('shop-dl:'.$token);

        return $sellId ? (int) $sellId : null;
    }

    public static function temporaryUrl(int $sellId, ?string $binary = null): ?string
    {
        if (! self::ensure($sellId, $binary)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        Cache::put('shop-dl:'.$token, $sellId, now()->addMinutes(self::TTL_MINUTES));

        return rtrim((string) config('app.url'), '/').'/api/shop-receipt-files/'.$token.'/recibo.pdf';
    }
}
