<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="format-detection" content="telephone=no,date=no,address=no,email=no" />
    <title>MTicket — recibo da loja</title>
    <style type="text/css">
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-spacing: 0; border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; display: block; }
        a { color: #08a9e6; text-decoration: none; }
        .preheader { display: none !important; visibility: hidden; opacity: 0; color: transparent; height: 0; width: 0; overflow: hidden; mso-hide: all; }
        @media screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .hero { height: auto !important; }
            .hero-img { width: 100% !important; height: auto !important; }
        }
    </style>
</head>
@php
    $buyer = $sell?->name ?? 'Cliente';
    $eventName = $event?->name ?? 'Evento';
    $total = number_format((float) ($sell?->total ?? 0), 2, ',', ' ').' MT';
    $reference = $sell?->transaction?->reference ?? null;
    $startDate = ! empty($event?->start_date) ? \Illuminate\Support\Carbon::parse($event->start_date)->locale('pt')->translatedFormat('d M Y') : null;
    $startTime = ! empty($event?->start_time) ? substr((string) $event->start_time, 0, 5) : null;
    $location = collect([$event?->address, $event?->city?->name, $event?->province?->name])->filter()->implode(', ');
    $image = ! empty($event?->image) ? 'https://backend.mticket.co.mz/storage/'.$event->image : null;
    $firstName = explode(' ', trim((string) $buyer))[0] ?: $buyer;
    $itemCount = (int) ($sell?->details?->sum('qtd') ?? 0);
@endphp
<body style="margin:0;padding:0;background-color:#e8f3f9;">
    <span class="preheader">O recibo da loja de {{ $eventName }} está pronto. PDF em anexo.</span>

    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#e8f3f9;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" border="0" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">
                    <tr>
                        <td align="left" style="padding:0 8px 16px 8px;font-family:Arial,Helvetica,sans-serif;">
                            <a href="https://mticket.co.mz" style="text-decoration:none;">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#08a9e6;vertical-align:middle;margin-right:8px;"></span>
                                <span style="font-size:18px;font-weight:800;color:#0a2540;letter-spacing:0.2px;vertical-align:middle;">MTicket</span>
                            </a>
                            <div style="font-size:12px;color:#5b6472;padding-top:4px;padding-left:18px;">Crie Momentos e Aproxime Pessoas.</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(10,37,64,0.08);">
                            <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:linear-gradient(160deg,#08a9e6 0%,#0678a8 100%);background-color:#08a9e6;padding:14px 24px;font-family:Arial,Helvetica,sans-serif;color:#ffffff;font-size:12px;font-weight:700;letter-spacing:1.4px;">
                                        LOJA DO EVENTO · COMPRA CONFIRMADA
                                    </td>
                                </tr>

                                @if($image)
                                <tr>
                                    <td class="hero" style="background-color:#0a2540;">
                                        <img class="hero-img" src="{{ $image }}" alt="{{ $eventName }}" width="600" style="width:100%;max-width:600px;height:auto;display:block;" />
                                    </td>
                                </tr>
                                @endif

                                <tr>
                                    <td class="px" style="padding:32px 36px 8px 36px;font-family:Arial,Helvetica,sans-serif;">
                                        <p style="margin:0 0 8px 0;font-size:13px;color:#08a9e6;font-weight:700;letter-spacing:0.6px;">Olá, {{ $firstName }}</p>
                                        <h1 style="margin:0 0 12px 0;font-size:26px;line-height:1.25;color:#0a2540;font-weight:800;">O teu recibo está pronto.</h1>
                                        <p style="margin:0;font-size:15px;line-height:1.6;color:#5b6472;">
                                            O pagamento da loja de <strong style="color:#0a2540;">{{ $eventName }}</strong> foi confirmado.
                                            O PDF com o QR de levantamento segue em anexo — apresenta-o no evento.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px" style="padding:24px 36px;">
                                        <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#f4fafd;border:1px solid #d7e8f2;border-radius:14px;">
                                            <tr>
                                                <td style="padding:20px 22px;font-family:Arial,Helvetica,sans-serif;">
                                                    <p style="margin:0 0 14px 0;font-size:11px;font-weight:700;letter-spacing:1.2px;color:#08a9e6;">DETALHES</p>
                                                    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0a2540;">
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;width:38%;">Evento</td>
                                                            <td style="padding:6px 0;font-weight:700;">{{ $eventName }}</td>
                                                        </tr>
                                                        @if($startDate)
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Data</td>
                                                            <td style="padding:6px 0;font-weight:700;">{{ $startDate }}@if($startTime) · {{ $startTime }}@endif</td>
                                                        </tr>
                                                        @endif
                                                        @if($location)
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Levantamento</td>
                                                            <td style="padding:6px 0;font-weight:700;">{{ $location }}</td>
                                                        </tr>
                                                        @endif
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Itens</td>
                                                            <td style="padding:6px 0;font-weight:700;">{{ $itemCount }} {{ $itemCount === 1 ? 'artigo' : 'artigos' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Código</td>
                                                            <td style="padding:6px 0;font-weight:800;letter-spacing:1px;">{{ $sell?->qrcode }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Total</td>
                                                            <td style="padding:6px 0;font-weight:800;color:#08a9e6;">{{ $total }}</td>
                                                        </tr>
                                                        @if($reference)
                                                        <tr>
                                                            <td style="padding:6px 0;color:#5b6472;">Referência</td>
                                                            <td style="padding:6px 0;font-weight:700;letter-spacing:0.3px;">{{ $reference }}</td>
                                                        </tr>
                                                        @endif
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                @if($sell?->details?->isNotEmpty())
                                <tr>
                                    <td class="px" style="padding:0 36px 16px 36px;font-family:Arial,Helvetica,sans-serif;">
                                        <p style="margin:0 0 10px 0;font-size:11px;font-weight:700;letter-spacing:1.2px;color:#08a9e6;">ENCOMENDA</p>
                                        @foreach ($sell->details as $line)
                                            <p style="margin:0 0 8px 0;font-size:14px;color:#0a2540;">
                                                {{ (int) $line->qtd }}× {{ $line->product_name }}
                                                <span style="color:#5b6472;"> — {{ number_format((float) $line->total, 2, ',', ' ') }} MT</span>
                                            </p>
                                        @endforeach
                                    </td>
                                </tr>
                                @endif

                                <tr>
                                    <td class="px" style="padding:0 36px 8px 36px;">
                                        <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#08a9e6;border-radius:14px;">
                                            <tr>
                                                <td style="padding:18px 22px;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
                                                    <p style="margin:0 0 4px 0;font-size:15px;font-weight:800;">PDF em anexo</p>
                                                    <p style="margin:0;font-size:13px;line-height:1.5;opacity:0.92;">Guarda o ficheiro no telemóvel. O QR Code é o comprovativo para levantar no evento.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px" style="padding:20px 36px 8px 36px;font-family:Arial,Helvetica,sans-serif;">
                                        <p style="margin:0 0 10px 0;font-size:11px;font-weight:700;letter-spacing:1.2px;color:#08a9e6;">NO EVENTO</p>
                                        <p style="margin:0 0 8px 0;font-size:14px;line-height:1.6;color:#5b6472;">1. Abre o PDF em anexo no telemóvel</p>
                                        <p style="margin:0 0 8px 0;font-size:14px;line-height:1.6;color:#5b6472;">2. Mostra o QR Code ao staff — com o ecrã no brilho máximo</p>
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#5b6472;">3. O levantamento é só no evento</p>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px" style="padding:28px 36px 32px 36px;font-family:Arial,Helvetica,sans-serif;">
                                        <a href="https://mticket.co.mz/meusbilhetes" style="display:inline-block;background:#0a2540;color:#ffffff;font-size:14px;font-weight:700;padding:12px 22px;border-radius:999px;">Abrir MTicket</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 12px 8px 12px;font-family:Arial,Helvetica,sans-serif;text-align:center;">
                            <p style="margin:0 0 8px 0;font-size:13px;color:#5b6472;">Precisas de ajuda?</p>
                            <p style="margin:0 0 4px 0;font-size:13px;">
                                <a href="mailto:suporte@mticket.co.mz" style="color:#0a2540;font-weight:700;">suporte@mticket.co.mz</a>
                            </p>
                            <p style="margin:0 0 16px 0;font-size:13px;color:#5b6472;">
                                <a href="tel:+258842648618" style="color:#5b6472;">+258 84 264 8618</a>
                                &nbsp;·&nbsp;
                                <a href="tel:+258842280974" style="color:#5b6472;">+258 84 228 0974</a>
                            </p>
                            <p style="margin:0;font-size:11px;line-height:1.5;color:#8a94a1;">
                                MTicket · Moçambique<br />
                                Este email foi enviado porque concluíste uma compra em mticket.co.mz
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
