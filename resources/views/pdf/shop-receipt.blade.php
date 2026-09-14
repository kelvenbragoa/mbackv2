<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Recibo da loja — {{ $event->name }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; }
        body { margin: 0; color: #0a2540; font-size: 12px; }
        .brand { color: #08a9e6; font-size: 11px; font-weight: 700; letter-spacing: 1.4px; }
        .muted { color: #5b6472; }
        .title { font-size: 22px; font-weight: 800; margin: 4px 0 0; }
        table { border-collapse: collapse; width: 100%; }
        .header td { vertical-align: top; }
        .hero { width: 118px; height: 78px; object-fit: cover; border-radius: 8px; background: #0a2540; }
        .box { border: 1px solid #d7e8f2; background: #f4fafd; border-radius: 10px; padding: 12px 14px; }
        .items th { text-align: left; font-size: 10px; letter-spacing: 0.8px; color: #08a9e6; padding: 8px 0; border-bottom: 1px solid #d7e8f2; }
        .items td { padding: 8px 0; border-bottom: 1px solid #edf3f8; vertical-align: top; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: 800; color: #08a9e6; }
        .code { font-size: 18px; font-weight: 800; letter-spacing: 2px; }
        .footer { margin-top: 22px; font-size: 10px; color: #8a94a1; text-align: center; }
        .badge { display: inline-block; background: #08a9e6; color: #fff; font-size: 10px; font-weight: 700; letter-spacing: 1px; padding: 5px 10px; border-radius: 999px; }
    </style>
</head>
@php
    $buyer = $sell->name ?: 'Cliente';
    $startDate = ! empty($event->start_date) ? \Illuminate\Support\Carbon::parse($event->start_date)->locale('pt')->translatedFormat('d M Y') : null;
    $startTime = ! empty($event->start_time) ? substr((string) $event->start_time, 0, 5) : null;
    $location = collect([$event->address, $event->city->name ?? null, $event->province->name ?? null])->filter()->implode(', ');
    $paidAt = $sell->created_at ? $sell->created_at->locale('pt')->translatedFormat('d M Y H:i') : null;
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ').' MT';
@endphp
<body>
    <table class="header" width="100%">
        <tr>
            <td>
                <div class="brand">MTICKET</div>
                <div class="title">Recibo da loja</div>
                <p class="muted" style="margin:6px 0 0;">Compra confirmada · levantamento no evento</p>
            </td>
            <td class="right" style="width:160px;">
                <span class="badge">PAGO</span>
                @if($paidAt)
                    <p class="muted" style="margin:8px 0 0;">{{ $paidAt }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-top:18px;">
        <tr>
            @if($eventImage)
                <td style="width:130px;">
                    <img src="{{ $eventImage }}" alt="" class="hero">
                </td>
            @endif
            <td>
                <p class="brand" style="margin:0 0 4px;">EVENTO</p>
                <div style="font-size:16px;font-weight:800;">{{ $event->name }}</div>
                @if($startDate)
                    <p class="muted" style="margin:6px 0 0;">{{ $startDate }}@if($startTime) · {{ $startTime }}@endif</p>
                @endif
                @if($location)
                    <p class="muted" style="margin:4px 0 0;">{{ $location }}</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:16px;">
        <table width="100%">
            <tr>
                <td style="width:50%;">
                    <p class="brand" style="margin:0 0 6px;">COMPRADOR</p>
                    <p style="margin:0;font-weight:700;">{{ $buyer }}</p>
                    <p class="muted" style="margin:4px 0 0;">{{ $sell->email }}</p>
                    <p class="muted" style="margin:4px 0 0;">{{ $sell->mobile }}</p>
                </td>
                <td>
                    <p class="brand" style="margin:0 0 6px;">PAGAMENTO</p>
                    <p style="margin:0;font-weight:700;">M-Pesa</p>
                    @if($sell->transaction?->reference)
                        <p class="muted" style="margin:4px 0 0;">Ref. {{ $sell->transaction->reference }}</p>
                    @endif
                    <p class="muted" style="margin:4px 0 0;">Sem envio · levantamento no evento</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="items" style="margin-top:18px;">
        <thead>
            <tr>
                <th>Artigo</th>
                <th class="right" style="width:50px;">Qtd</th>
                <th class="right" style="width:90px;">Preço</th>
                <th class="right" style="width:100px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sell->details as $line)
                <tr>
                    <td>{{ $line->product_name }}</td>
                    <td class="right">{{ (int) $line->qtd }}</td>
                    <td class="right">{{ $money($line->price) }}</td>
                    <td class="right">{{ $money($line->total) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="right" style="border-bottom:0;padding-top:14px;font-weight:700;">Total</td>
                <td class="right total" style="border-bottom:0;padding-top:14px;">{{ $money($sell->total) }}</td>
            </tr>
        </tbody>
    </table>

    <table width="100%" style="margin-top:22px;border:1px solid #d7e8f2;border-radius:10px;">
        <tr>
            @if($qrMarkup)
                <td style="width:170px;padding:16px;text-align:center;">
                    {!! $qrMarkup !!}
                </td>
            @endif
            <td style="padding:16px 18px;">
                <p class="brand" style="margin:0 0 6px;">LEVANTAMENTO</p>
                <div class="code">{{ $sell->qrcode }}</div>
                <p class="muted" style="margin:8px 0 0;line-height:1.5;">
                    Mostra este QR ou código no evento.
                    Sem este recibo não é possível levantar a encomenda.
                </p>
            </td>
        </tr>
    </table>

    <p class="footer">MTicket · Moçambique · Este documento confirma o pagamento da loja do evento.</p>
</body>
</html>
