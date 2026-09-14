<?php

namespace App\Support;

use App\Models\SellShop;
use Barryvdh\DomPDF\Facade\Pdf;

class ShopOrderPdf
{
    public static function forSell(SellShop $sell)
    {
        $sell->loadMissing(['details', 'event.province', 'event.city', 'transaction']);
        $event = $sell->event;

        if (! $event || ! $sell->isPaid()) {
            return null;
        }

        return Pdf::loadView('pdf.shop-receipt', [
            'sell' => $sell,
            'event' => $event,
            'eventImage' => TicketPdf::eventImageDataUri($event->image ?? null),
            'qrMarkup' => TicketPdf::qrMarkup((string) $sell->qrcode, 150),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ]);
    }

    public static function filename(SellShop $sell): string
    {
        $code = preg_replace('/[^A-Z0-9\-]/i', '', (string) $sell->qrcode) ?: (string) $sell->id;

        return 'MTicket-recibo-'.$code.'.pdf';
    }

    public static function download(SellShop $sell)
    {
        $pdf = self::forSell($sell);
        if (! $pdf) {
            return null;
        }

        return $pdf->download(self::filename($sell));
    }
}
