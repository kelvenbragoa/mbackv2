<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Bilhete MTicket</title>
    <style>
        @page { margin: 6px; }
        * { margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0a2540;
            font-size: 11px;
        }
        .page {
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .ticket {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }
        .col-image {
            width: 28%;
            height: 250px;
            background-color: #0a2540;
            color: #ffffff;
            vertical-align: bottom;
            padding: 0;
        }
        .col-image img.cover {
            width: 210px;
            height: 250px;
            display: block;
        }
        .image-caption {
            position: relative;
            padding: 12px 14px 16px;
            color: #ffffff;
        }
        .brand {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }
        .brand-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            background: #ffffff;
            border-radius: 50%;
        }
        .event-title {
            font-size: 15px;
            font-weight: bold;
            line-height: 1.2;
            color: #ffffff;
        }
        .col-info {
            width: 48%;
            vertical-align: top;
            padding: 16px 18px;
            border-right: 1px dashed #c9d8e4;
        }
        .logo-mark {
            width: 26px;
            height: 26px;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            line-height: 26px;
        }
        .logo-text {
            font-size: 15px;
            font-weight: bold;
            color: #0a2540;
            padding-left: 8px;
        }
        .logo-text span { color: #08a9e6; }
        .slogan {
            font-size: 9px;
            font-style: italic;
            color: #5b6472;
            padding: 4px 0 10px;
        }
        .eyebrow {
            font-size: 8px;
            font-weight: bold;
            color: #08a9e6;
            letter-spacing: 1px;
        }
        .event-name {
            font-size: 13px;
            font-weight: bold;
            color: #0a2540;
            padding: 2px 0 10px;
            line-height: 1.25;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px dashed #e3e9ef;
        }
        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 7px 8px 0 0;
        }
        .info-label {
            font-size: 8px;
            font-weight: bold;
            color: #5b6472;
        }
        .info-value {
            font-size: 10px;
            font-weight: bold;
            color: #0a2540;
            padding-top: 1px;
        }
        .badge {
            display: inline-block;
            background: #e7f8ee;
            color: #159a52;
            font-size: 8px;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 10px;
        }
        .col-qr {
            width: 24%;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
            padding: 14px 10px;
        }
        .qr-caption {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.8px;
            opacity: 0.9;
        }
        .qr-code-id {
            font-size: 11px;
            font-weight: bold;
            padding: 4px 0 8px;
        }
        .qr-box {
            background: #ffffff;
            padding: 8px;
            display: inline-block;
        }
        .qr-box img {
            width: 110px;
            height: 110px;
            display: block;
        }
        .qr-instruction {
            font-size: 8px;
            font-weight: bold;
            padding-top: 8px;
        }
        .qr-subtext {
            font-size: 8px;
            padding-top: 4px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
@php
    $months = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    $eventImage = \App\Support\TicketPdf::eventImageDataUri($event->image ?? null);
@endphp

@foreach ($detail as $item)
    @php
        $evt = $item->event ?? $event;
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
        $code = $item->ticket_number ?: '#0'.$item->id;
        $buyer = $item->sell->name ?? $item->name ?? 'Cliente';
        $price = number_format((float) ($item->sell->price ?? $item->price ?? 0), 0, ',', '.').' MT';
        $qrPayload = $item->qrcode ?: json_encode([
            's' => $item->status,
            'i' => $item->id,
            'ie' => $evt->id,
        ]);
        $qrMarkup = \App\Support\TicketPdf::qrMarkup($qrPayload);
        $itemImage = $eventImage;
        if (($evt->image ?? null) && $evt->image !== ($event->image ?? null)) {
            $itemImage = \App\Support\TicketPdf::eventImageDataUri($evt->image);
        }
    @endphp

    <div class="page">
        <table class="ticket" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="col-image">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="padding:0; height:175px; background-color:#0a2540;">
                                @if ($itemImage)
                                    <img src="{{ $itemImage }}" width="210" height="175" style="display:block;" alt="">
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color:#0a2540; color:#ffffff; padding:10px 12px 12px;">
                                <div class="brand">• MTicket</div>
                                <div class="event-title">{{ $evt->name }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="col-info">
                    <table cellspacing="0" cellpadding="0">
                        <tr>
                            <td class="logo-mark">M</td>
                            <td class="logo-text">M<span>Ticket</span></td>
                        </tr>
                    </table>
                    <div class="slogan">Crie Momentos e Aproxime Pessoas.</div>
                    <div class="eyebrow">EVENTO</div>
                    <div class="event-name">{{ $evt->name }}</div>
                    <table class="info-table" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <div class="info-label">Data</div>
                                <div class="info-value">{{ $dateLabel }}</div>
                            </td>
                            <td>
                                <div class="info-label">Hora</div>
                                <div class="info-value">{{ $timeLabel }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="info-label">Local</div>
                                <div class="info-value">{{ $location }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="info-label">Tipo de bilhete</div>
                                <div class="info-value">{{ $item->ticket->name ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="info-label">Comprador</div>
                                <div class="info-value">{{ $buyer }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="info-label">Preço</div>
                                <div class="info-value">{{ $price }}</div>
                            </td>
                            <td>
                                <div class="badge">BILHETE VÁLIDO</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="col-qr">
                    <div class="qr-caption">CÓDIGO DO BILHETE</div>
                    <div class="qr-code-id">{{ $code }}</div>
                    <div class="qr-box">
                        {!! $qrMarkup !!}
                    </div>
                    <div class="qr-instruction">APONTE O QR CODE NA ENTRADA</div>
                    <div class="qr-subtext">Bilhete digital<br>Apresente o QR Code na entrada</div>
                </td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
