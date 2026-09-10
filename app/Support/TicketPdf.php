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

    /**
     * Same horizontal strip as the original ticket PDF (fills the page).
     */
    public const BOCA_PAPER = [0, 0, 780, 278];

    public static function make(Collection|array $details, Event $event)
    {
        return Pdf::loadView('pdf.ticket', [
            'detail' => $details,
            'event' => $event,
        ])
            ->setPaper(self::PAPER, 'portrait')
            ->setOptions(self::pdfOptions());
    }

    public static function makeBoca(Collection|array $details, Event $event)
    {
        return Pdf::loadView('pdf.ticket', [
            'detail' => $details,
            'event' => $event,
        ])
            ->setPaper(self::PAPER, 'portrait')
            ->setOptions(self::pdfOptions());
    }

    public static function makeA4(Collection|array $details, Event $event)
    {
        return Pdf::loadView('pdf.batch-a4', [
            'detail' => $details,
            'event' => $event,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(self::pdfOptions());
    }

    private static function pdfOptions(): array
    {
        return [
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'dpi' => 96,
        ];
    }

    public static function forSellId(int $sellId)
    {
        return self::forSellQuery(
            SellDetails::with(['event.province', 'event.city', 'ticket', 'sell'])
                ->where('sell_id', $sellId)
        );
    }

    public static function forBatchSellId(int $sellId, string $layout = 'a4')
    {
        $details = self::batchDetails($sellId);
        $event = $details->first()?->event;

        if ($details->isEmpty() || ! $event) {
            return null;
        }

        return $layout === 'boca'
            ? self::makeBoca($details, $event)
            : self::makeA4($details, $event);
    }

    private static function batchDetails(int $sellId): Collection
    {
        return SellDetails::with(['event.province', 'event.city', 'ticket', 'sell'])
            ->where('sell_id', $sellId)
            ->where('status', SellDetails::STATUS_VALID)
            ->orderBy('id')
            ->get()
            ->filter(function (SellDetails $detail) {
                return ! ($detail->ticket && (int) $detail->ticket->is_live === 1);
            })
            ->values();
    }

    private static function forSellQuery($query)
    {
        $details = $query
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
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        if ($path === '') {
            return '';
        }

        $full = storage_path('app/public/'.$path);
        if (! is_file($full)) {
            return '';
        }

        $bytes = (string) file_get_contents($full);
        if ($bytes === '') {
            return '';
        }

        $jpeg = self::toJpegBytes($bytes);
        if ($jpeg !== '') {
            return 'data:image/jpeg;base64,'.base64_encode($jpeg);
        }

        $mime = mime_content_type($full) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private static function toJpegBytes(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return '';
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return '';
        }

        $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($trueColor, 255, 255, 255);
        imagefill($trueColor, 0, 0, $white);
        imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        ob_start();
        imagejpeg($trueColor, null, 86);
        $jpeg = (string) ob_get_clean();
        imagedestroy($trueColor);

        return $jpeg;
    }

    public static function qrMarkup(string $payload, int $size = 110): string
    {
        $pngSize = max(80, (int) round($size * 1.25));

        try {
            $png = QrCode::format('png')->size($pngSize)->margin(1)->errorCorrection('H')->generate($payload);

            return '<img src="data:image/png;base64,'.base64_encode($png).'" width="'.$size.'" height="'.$size.'" alt="QR">';
        } catch (\Throwable) {
            return (string) QrCode::size($size)->margin(1)->errorCorrection('H')->generate($payload);
        }
    }

    /**
     * Shared labels for physical print layouts (BOCA / A4).
     */
    public static function printTicketData(SellDetails $item, Event $fallbackEvent, string $eventImage): array
    {
        $months = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
        $evt = $item->event ?? $fallbackEvent;
        $startDate = $evt->start_date ? strtotime($evt->start_date) : null;
        $dateLabel = $startDate
            ? date('d', $startDate).' '.$months[((int) date('n', $startDate)) - 1].' '.date('Y', $startDate)
            : '—';
        $startTime = $evt->start_time ? date('H:i', strtotime($evt->start_time)) : '—';
        $endTime = $evt->end_time ? date('H:i', strtotime($evt->end_time)) : null;
        $timeLabel = ($startTime !== '—' && $endTime) ? $startTime.' – '.$endTime : $startTime;
        $location = collect([$evt->address ?? null, $evt->city->name ?? null, $evt->province->name ?? null, 'Moçambique'])
            ->filter()
            ->implode(', ') ?: 'Local a anunciar';
        $code = $item->ticket_number ?: 'MTK-'.$item->id;
        $buyer = $item->sell->name ?? $item->name ?? 'Cliente';
        $price = number_format((float) ($item->sell->price ?? $item->ticket?->price ?? 0), 0, ',', '.').' MT';
        $qrPayload = $item->qrcode ?: json_encode([
            's' => $item->status,
            'i' => $item->id,
            'ie' => $evt->id,
        ]);
        $itemImage = $eventImage;
        if (($evt->image ?? null) && $evt->image !== ($fallbackEvent->image ?? null)) {
            $itemImage = self::eventImageDataUri($evt->image);
        }

        return [
            'evt' => $evt,
            'dateLabel' => $dateLabel,
            'timeLabel' => $timeLabel,
            'location' => $location,
            'code' => $code,
            'buyer' => $buyer,
            'price' => $price,
            'ticketName' => $item->ticket?->name ?? '—',
            'qrPayload' => $qrPayload,
            'itemImage' => $itemImage,
        ];
    }
}
