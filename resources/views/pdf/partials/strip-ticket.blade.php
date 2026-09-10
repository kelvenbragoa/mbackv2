@php
    $t = \App\Support\TicketPdf::printTicketData($item, $event, $eventImage);
    $qrSize = $qrSize ?? 88;
    $imgW = $imgW ?? 168;
    $imgH = $imgH ?? 230;
    $qr = \App\Support\TicketPdf::qrMarkup($t['qrPayload'], $qrSize);
@endphp
<table class="ticket" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td class="col-image">
            @if ($t['itemImage'])
                <img src="{{ $t['itemImage'] }}" width="{{ $imgW }}" height="{{ $imgH }}" alt="">
            @endif
            <div class="col-image__caption">
                <div class="brand">• MTicket</div>
                <div class="event-title">{{ $t['evt']->name }}</div>
            </div>
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
            <div class="event-name">{{ $t['evt']->name }}</div>
            <table class="info-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="info-label">Data</div>
                        <div class="info-value">{{ $t['dateLabel'] }}</div>
                    </td>
                    <td>
                        <div class="info-label">Hora</div>
                        <div class="info-value">{{ $t['timeLabel'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="info-label">Local</div>
                        <div class="info-value">{{ $t['location'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="info-label">Tipo de bilhete</div>
                        <div class="info-value">{{ $t['ticketName'] }}</div>
                    </td>
                    <td>
                        <div class="info-label">Lote</div>
                        <div class="info-value">{{ $t['buyer'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="info-label">Preço</div>
                        <div class="info-value">{{ $t['price'] }}</div>
                    </td>
                    <td>
                        <div class="badge">BILHETE VÁLIDO</div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="col-qr">
            <div class="qr-caption">CÓDIGO DO BILHETE</div>
            <div class="qr-code-id">{{ $t['code'] }}</div>
            <div class="qr-box">{!! $qr !!}</div>
            <div class="qr-instruction">APONTE O QR CODE NA ENTRADA</div>
        </td>
    </tr>
</table>
