<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Lote BOCA 6.00x3.25</title>
    <style>
        @page { size: 6in 3.25in; margin: 0; }
        * { margin: 0; padding: 0; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #0a2540;
            font-size: 10px;
        }
        .page {
            width: 6in;
            height: 3.25in;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .page-break {
            page-break-before: always;
        }

        .ticket {
            width: 6in;
            height: 3.25in;
            border-collapse: collapse;
            table-layout: fixed;
            background: #ffffff;
        }
        .col-image {
            width: 28%;
            height: 3.25in;
            background-color: #0a2540;
            color: #ffffff;
            vertical-align: top;
            padding: 0;
        }
        .col-image img {
            display: block;
            width: 1.68in;
            height: 2.35in;
        }
        .col-image__caption {
            background-color: #0a2540;
            color: #ffffff;
            padding: 6px 8px 8px;
        }
        .brand {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }
        .event-title {
            font-size: 10px;
            font-weight: bold;
            line-height: 1.15;
            color: #ffffff;
        }
        .col-info {
            width: 48%;
            height: 3.25in;
            vertical-align: top;
            padding: 8px 10px;
            border-right: 1px dashed #c9d8e4;
        }
        .logo-mark {
            width: 20px;
            height: 20px;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            line-height: 20px;
        }
        .logo-text {
            font-size: 12px;
            font-weight: bold;
            color: #0a2540;
            padding-left: 6px;
        }
        .logo-text span { color: #08a9e6; }
        .slogan {
            font-size: 7px;
            font-style: italic;
            color: #5b6472;
            padding: 2px 0 5px;
        }
        .eyebrow {
            font-size: 7px;
            font-weight: bold;
            color: #08a9e6;
            letter-spacing: 1px;
        }
        .event-name {
            font-size: 11px;
            font-weight: bold;
            color: #0a2540;
            padding: 1px 0 5px;
            line-height: 1.15;
        }
        .info-table { width: 100%; border-collapse: collapse; border-top: 1px dashed #e3e9ef; }
        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 3px 6px 0 0;
        }
        .info-label {
            font-size: 7px;
            font-weight: bold;
            color: #5b6472;
        }
        .info-value {
            font-size: 9px;
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
            padding: 3px 7px;
        }
        .col-qr {
            width: 24%;
            height: 3.25in;
            background: #08a9e6;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
            padding: 6px 6px;
        }
        .qr-caption {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .qr-code-id {
            font-size: 10px;
            font-weight: bold;
            padding: 2px 0 5px;
        }
        .qr-box {
            background: #ffffff;
            padding: 4px;
            display: inline-block;
        }
        .qr-box img {
            width: 88px;
            height: 88px;
            display: block;
        }
        .qr-instruction {
            font-size: 7px;
            font-weight: bold;
            padding-top: 5px;
        }
    </style>
</head>
<body>
@php
    $eventImage = \App\Support\TicketPdf::eventImageDataUri($event->image ?? null);
@endphp
@foreach ($detail as $item)
    <div class="page{{ $loop->first ? '' : ' page-break' }}">
        @include('pdf.partials.strip-ticket', [
            'item' => $item,
            'event' => $event,
            'eventImage' => $eventImage,
            'qrSize' => 88,
            'imgW' => 168,
            'imgH' => 170,
        ])
    </div>
@endforeach
</body>
</html>
