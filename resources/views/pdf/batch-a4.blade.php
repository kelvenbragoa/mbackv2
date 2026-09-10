<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Lote A4 recortar</title>
    <style>
        @page { size: A4 portrait; margin: 8mm 8mm 8mm 8mm; }
        * { margin: 0; padding: 0; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #0a2540;
            font-size: 9px;
        }
        .sheet {
            page-break-inside: avoid;
        }
        .sheet-break {
            page-break-before: always;
        }
        .hint {
            font-size: 8px;
            color: #5b6472;
            padding-bottom: 2mm;
        }
        .cut {
            width: 194mm;
            height: 64mm;
            border: 0.5pt dashed #94a3b8;
            overflow: hidden;
            margin: 0 0 3mm 0;
        }

        .ticket {
            width: 194mm;
            height: 64mm;
            border-collapse: collapse;
            table-layout: fixed;
            background: #ffffff;
        }
        .col-image {
            width: 28%;
            height: 64mm;
            background-color: #0a2540;
            color: #ffffff;
            vertical-align: top;
            padding: 0;
        }
        .col-image img {
            display: block;
            width: 54mm;
            height: 46mm;
        }
        .col-image__caption {
            background-color: #0a2540;
            color: #ffffff;
            padding: 3px 8px 4px;
        }
        .brand {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.4px;
            margin-bottom: 1px;
        }
        .event-title {
            font-size: 9px;
            font-weight: bold;
            line-height: 1.15;
            color: #ffffff;
        }
        .col-info {
            width: 48%;
            height: 64mm;
            vertical-align: top;
            padding: 5px 10px;
            border-right: 1px dashed #c9d8e4;
        }
        .logo-mark {
            width: 18px;
            height: 18px;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            line-height: 18px;
        }
        .logo-text {
            font-size: 12px;
            font-weight: bold;
            color: #0a2540;
            padding-left: 5px;
        }
        .logo-text span { color: #08a9e6; }
        .slogan {
            font-size: 7px;
            font-style: italic;
            color: #5b6472;
            padding: 1px 0 3px;
        }
        .eyebrow {
            font-size: 7px;
            font-weight: bold;
            color: #08a9e6;
            letter-spacing: 1px;
        }
        .event-name {
            font-size: 10px;
            font-weight: bold;
            color: #0a2540;
            padding: 0 0 3px;
            line-height: 1.15;
        }
        .info-table { width: 100%; border-collapse: collapse; border-top: 1px dashed #e3e9ef; }
        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 2px 6px 0 0;
        }
        .info-label {
            font-size: 6px;
            font-weight: bold;
            color: #5b6472;
        }
        .info-value {
            font-size: 8px;
            font-weight: bold;
            color: #0a2540;
            padding-top: 1px;
        }
        .badge {
            display: inline-block;
            background: #e7f8ee;
            color: #159a52;
            font-size: 7px;
            font-weight: bold;
            padding: 2px 6px;
        }
        .col-qr {
            width: 24%;
            height: 64mm;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
            padding: 4px 6px;
        }
        .qr-caption {
            font-size: 6px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .qr-code-id {
            font-size: 9px;
            font-weight: bold;
            padding: 1px 0 3px;
        }
        .qr-box {
            background: #ffffff;
            padding: 3px;
            display: inline-block;
        }
        .qr-box img {
            width: 72px;
            height: 72px;
            display: block;
        }
        .qr-instruction {
            font-size: 6px;
            font-weight: bold;
            padding-top: 3px;
        }
    </style>
</head>
<body>
@php
    $eventImage = \App\Support\TicketPdf::eventImageDataUri($event->image ?? null);
    $pages = $detail->chunk(4);
@endphp
@foreach ($pages as $pageTickets)
    <div class="sheet{{ $loop->first ? '' : ' sheet-break' }}">
        <div class="hint">A4 vertical · 4 bilhetes horizontais · recortar pela linha tracejada</div>
        @foreach ($pageTickets as $item)
            <div class="cut">
                @include('pdf.partials.strip-ticket', [
                    'item' => $item,
                    'event' => $event,
                    'eventImage' => $eventImage,
                    'qrSize' => 72,
                    'imgW' => 150,
                    'imgH' => 130,
                ])
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
