<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SellDetails;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketPdf
{
    /**
     * Landscape strip close to the on-screen ticket (not A4).
     * 780pt × 278pt ≈ 275mm × 98mm.
     */
    public const PAPER = [0, 0, 780, 278];

    public static function make(Collection|array $details, Event $event)
    {
        return Pdf::loadView('pdf.ticket', [
            'detail' => $details,
            'event' => $event,
        ])
            ->setPaper(self::PAPER, 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ]);
    }

    public static function forSellId(int $sellId)
    {
        $details = SellDetails::with(['event.province', 'event.city', 'ticket', 'sell'])
            ->where('sell_id', $sellId)
            ->get()
            ->filter(function (SellDetails $detail) {
                return ! ($detail->ticket && (int) $detail->ticket->is_live === 1);
            })
            ->values();

        $event = $details->first()?->event;

        if ($details->isEmpty() || ! $event) {
            return null;
        }

        return self::make($details, $event);
    }

    public static function eventImageDataUri(?string $path): string
    {
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return '';
        }

        $full = storage_path('app/public/'.$path);
        if (! is_file($full)) {
            return '';
        }

        $mime = mime_content_type($full) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }

    public static function qrMarkup(string $payload): string
    {
        try {
            $png = QrCode::format('png')->size(140)->margin(1)->errorCorrection('H')->generate($payload);

            return '<img src="data:image/png;base64,'.base64_encode($png).'" width="110" height="110" alt="QR">';
        } catch (\Throwable) {
            return (string) QrCode::size(110)->margin(1)->errorCorrection('H')->generate($payload);
        }
    }
}
