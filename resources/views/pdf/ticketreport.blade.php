@php
    $mt = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' MT';
    $num = fn ($value) => number_format((int) $value, 0, ',', ' ');
    $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1) : 0;
    $pctLabel = fn ($value) => str_replace('.', ',', (string) $value) . '%';

    $onlineQty = (int) $tickets_online->sum('qty');
    $onlineTotal = (float) $tickets_online->sum('total');
    $boxQty = (int) $tickets_box_office->sum('qty');
    $boxTotal = (float) $tickets_box_office->sum('total');
    $loteQty = (int) $tickets_lote->sum('qty');
    $loteTotal = (float) $tickets_lote->sum('total');
    $soldQty = $onlineQty + $boxQty + $loteQty;
    $soldTotal = $onlineTotal + $boxTotal + $loteTotal;

    $localQty = $boxQty + $loteQty;
    $validated = $tickets_local_validated + $tickets_online_validated;
    $pending = $tickets_local_pending + $tickets_online_pending;

    $channelRows = [
        ['label' => 'Online', 'hint' => 'Website e aplicação', 'qty' => $onlineQty, 'total' => $onlineTotal],
        ['label' => 'Bilheteira', 'hint' => 'Venda física pelos protocolos', 'qty' => $boxQty, 'total' => $boxTotal],
        ['label' => 'Lotes', 'hint' => 'Bilhetes emitidos para revenda', 'qty' => $loteQty, 'total' => $loteTotal],
    ];

    $ticketRows = $event->tickets->map(fn ($t) => [$t, 'Bilhete'])
        ->concat($event->packages->map(fn ($t) => [$t, 'Pacote']));

    $startDate = $event->start_date ? \Carbon\Carbon::parse($event->start_date) : null;
    $endDate = $event->end_date ? \Carbon\Carbon::parse($event->end_date) : null;
    $dateLabel = $startDate ? $startDate->format('d/m/Y') : '—';
    if ($endDate && $startDate && ! $endDate->isSameDay($startDate)) {
        $dateLabel .= ' a ' . $endDate->format('d/m/Y');
    }
    $timeLabel = $event->start_time ? substr($event->start_time, 0, 5) : null;
    $place = collect([$event->location ?: $event->address, $event->city->name ?? null, $event->province->name ?? null])
        ->filter()->unique()->implode(', ');
@endphp
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório de vendas — {{ $event->name }}</title>

    <style type="text/css">
        @page { margin: 26mm 14mm 20mm 14mm; }

        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #0a2540; font-size: 9px; line-height: 1.35; }

        .page-header { position: fixed; top: -19mm; left: 0; right: 0; height: 12mm; border-bottom: 1px solid #dbe5ee; }
        .page-header table { width: 100%; border-collapse: collapse; }
        .logo-mark { width: 22px; height: 22px; background: #08a9e6; color: #fff; text-align: center; font-weight: bold; font-size: 12px; line-height: 22px; }
        .logo-text { font-size: 14px; font-weight: bold; color: #0a2540; padding-left: 7px; }
        .logo-text span { color: #08a9e6; }
        .page-header .doc { text-align: right; font-size: 8px; color: #5b6472; }
        .page-header .doc strong { color: #0a2540; }

        .page-footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 8mm; border-top: 1px solid #dbe5ee; padding-top: 5px; font-size: 7.5px; color: #8692a1; }
        .page-footer table { width: 100%; border-collapse: collapse; }
        .hero { background: #0a2540; color: #fff; padding: 18px 20px; }
        .hero table { width: 100%; border-collapse: collapse; }
        .hero .eyebrow { color: #08a9e6; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; }
        .hero .title { font-size: 20px; font-weight: bold; margin: 5px 0 8px; line-height: 1.15; }
        .hero .meta { font-size: 8.5px; color: #c4d3e0; }
        .hero .meta strong { color: #fff; }
        .hero .side { text-align: right; vertical-align: top; font-size: 8.5px; color: #c4d3e0; }
        .hero .side .label { color: #08a9e6; font-size: 7px; font-weight: bold; letter-spacing: 1px; }
        .hero .side .value { color: #fff; font-size: 10px; font-weight: bold; margin-bottom: 8px; }
        .hero-accent { height: 4px; background: #08a9e6; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 16px -8px 0; }
        .kpi { width: 25%; background: #f4f8fb; border-top: 3px solid #08a9e6; padding: 10px 12px; vertical-align: top; }
        .kpi.dark { background: #0a2540; border-top-color: #08a9e6; color: #fff; }
        .kpi .label { font-size: 7px; font-weight: bold; letter-spacing: 1px; color: #5b6472; text-transform: uppercase; }
        .kpi.dark .label { color: #8fd8f5; }
        .kpi .value { font-size: 16px; font-weight: bold; margin: 4px 0 2px; }
        .kpi .sub { font-size: 7.5px; color: #5b6472; }
        .kpi.dark .sub { color: #c4d3e0; }

        .section { margin-top: 20px; page-break-inside: avoid; }
        table.data tr { page-break-inside: avoid; }
        .section-title { font-size: 12px; font-weight: bold; color: #0a2540; padding-bottom: 5px; border-bottom: 2px solid #0a2540; margin-bottom: 8px; }
        .section-title span { color: #08a9e6; font-size: 8px; letter-spacing: 1px; font-weight: bold; display: block; margin-bottom: 2px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #eef3f8; color: #5b6472; font-size: 7px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; text-align: left; padding: 6px 8px; border-bottom: 1px solid #dbe5ee; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #edf1f5; vertical-align: middle; }
        table.data tbody tr:nth-child(even) td { background: #fafcfd; }
        table.data tfoot tr { background: #0a2540; }
        table.data tfoot td { font-weight: bold; background: #0a2540; color: #fff; padding: 7px 8px; border: 1px solid #0a2540; }
        table.data .r { text-align: right; white-space: nowrap; }
        table.data .c { text-align: center; }
        table.data .muted { color: #8692a1; font-size: 7.5px; }
        .bar { width: 100%; height: 6px; background: #e3edf5; }
        .bar div { height: 6px; background: #08a9e6; }

        .stat-grid { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
        .stat-grid td { background: #f4f8fb; padding: 8px 10px; text-align: center; }
        .stat-grid .n { font-size: 14px; font-weight: bold; }
        .stat-grid .l { font-size: 7px; color: #5b6472; letter-spacing: 0.6px; text-transform: uppercase; }

        .note { margin-top: 8px; padding: 7px 10px; background: #fff8e6; border-left: 3px solid #f2b705; font-size: 7.5px; color: #6b5200; }

    </style>
</head>
<body>

<div class="page-header">
    <table>
        <tr>
            <td style="width: 50%;">
                <table style="border-collapse: collapse;">
                    <tr>
                        {{-- <td class="logo-mark">M</td> --}}
                        <td class="logo-text">M<span>Ticket</span></td>
                    </tr>
                </table>
            </td>
            <td class="doc">
                <strong>Relatório de vendas de bilhetes</strong><br>
                {{ $event->name }}
            </td>
        </tr>
    </table>
</div>

<div class="page-footer">
    <table>
        <tr>
            <td>Emitido em {{ now()->format('d/m/Y \à\s H:i') }} &middot; MTicket &middot; ConnectUs LTD, Beira, Moçambique &middot; www.mticket.co.mz</td>
        </tr>
    </table>
</div>

<div class="hero">
    <table>
        <tr>
            <td style="vertical-align: top;">
                <div class="eyebrow">RELATÓRIO DE VENDAS DE BILHETES</div>
                <div class="title">{{ $event->name }}</div>
                <div class="meta">
                    <strong>Data:</strong> {{ $dateLabel }}@if ($timeLabel) &middot; {{ $timeLabel }}@endif
                    @if ($place)
                        <br><strong>Local:</strong> {{ $place }}
                    @endif
                </div>
            </td>
            <td class="side" style="width: 34%;">
                <div class="label">PROMOTOR</div>
                <div class="value">{{ ($event->user->company_name ?? null) ?: ($event->user->name ?? '—') }}</div>
                <div class="label">EMITIDO EM</div>
                <div class="value" style="margin-bottom: 0;">{{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="hero-accent"></div>

<table class="kpis">
    <tr>
        <td class="kpi dark">
            <div class="label">Receita bruta</div>
            <div class="value">{{ $mt($soldTotal) }}</div>
            <div class="sub">Preço médio {{ $mt($soldQty > 0 ? $soldTotal / $soldQty : 0) }}</div>
        </td>
        <td class="kpi">
            <div class="label">Bilhetes vendidos</div>
            <div class="value">{{ $num($soldQty) }}</div>
            <div class="sub">Online {{ $num($onlineQty) }} &middot; Físico {{ $num($localQty) }}</div>
        </td>
        <td class="kpi">
            <div class="label">Entradas validadas</div>
            <div class="value">{{ $num($validated) }}</div>
            <div class="sub">{{ $pctLabel($pct($validated, $validated + $pending)) }} dos bilhetes vendidos</div>
        </td>
        <td class="kpi">
            <div class="label">Por validar</div>
            <div class="value">{{ $num($pending) }}</div>
            <div class="sub">Devolvidos (lotes): {{ $num($tickets_returned) }}</div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title"><span>CANAIS</span>Vendas por canal</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30%;">Canal</th>
                <th class="r" style="width: 13%;">Bilhetes</th>
                <th class="r" style="width: 20%;">Receita</th>
                <th style="width: 37%;">Participação na receita</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($channelRows as $row)
            <tr>
                <td><strong>{{ $row['label'] }}</strong><br><span class="muted">{{ $row['hint'] }}</span></td>
                <td class="r">{{ $num($row['qty']) }}</td>
                <td class="r">{{ $mt($row['total']) }}</td>
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0; border: 0; background: none;"><div class="bar"><div style="width: {{ $pct($row['total'], $soldTotal) }}%;"></div></div></td>
                            <td style="padding: 0 0 0 8px; border: 0; background: none; width: 38px;" class="r">{{ $pctLabel($pct($row['total'], $soldTotal)) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="r">{{ $num($soldQty) }}</td>
                <td class="r">{{ $mt($soldTotal) }}</td>
                <td class="r">100%</td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="section">
    <div class="section-title"><span>ACESSOS</span>Controlo de entradas</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30%;">Origem</th>
                <th class="r">Vendidos</th>
                <th class="r">Validados</th>
                <th class="r">Por validar</th>
                <th style="width: 30%;">Taxa de entrada</th>
            </tr>
        </thead>
        <tbody>
            @foreach ([
                ['Físicos (bilheteira e lotes)', $tickets_local_validated, $tickets_local_pending],
                ['Online', $tickets_online_validated, $tickets_online_pending],
            ] as [$label, $ok, $wait])
            <tr>
                <td><strong>{{ $label }}</strong></td>
                <td class="r">{{ $num($ok + $wait) }}</td>
                <td class="r">{{ $num($ok) }}</td>
                <td class="r">{{ $num($wait) }}</td>
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0; border: 0; background: none;"><div class="bar"><div style="width: {{ $pct($ok, $ok + $wait) }}%; background: #1fa463;"></div></div></td>
                            <td style="padding: 0 0 0 8px; border: 0; background: none; width: 38px;" class="r">{{ $pctLabel($pct($ok, $ok + $wait)) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="r">{{ $num($validated + $pending) }}</td>
                <td class="r">{{ $num($validated) }}</td>
                <td class="r">{{ $num($pending) }}</td>
                <td class="r">{{ $pctLabel($pct($validated, $validated + $pending)) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if ($lote_summary['issued'] > 0)
<div class="section">
    <div class="section-title"><span>REVENDA</span>Bilhetes em lote</div>
    <table class="stat-grid">
        <tr>
            <td><div class="n">{{ $num($lote_summary['issued']) }}</div><div class="l">Emitidos</div></td>
            <td><div class="n" style="color: #1fa463;">{{ $num($lote_summary['used']) }}</div><div class="l">Usados</div></td>
            <td><div class="n" style="color: #b07d00;">{{ $num($lote_summary['unused']) }}</div><div class="l">Por usar</div></td>
            <td><div class="n" style="color: #8692a1;">{{ $num($lote_summary['returned']) }}</div><div class="l">Devolvidos</div></td>
            <td><div class="n">{{ $mt($loteTotal) }}</div><div class="l">Valor a receber</div></td>
        </tr>
    </table>
    @if ($lote_summary['unused'] > 0)
    <div class="note">
        Os {{ $num($lote_summary['unused']) }} bilhetes de lote por usar e ainda não devolvidos estão contabilizados como vendidos.
        Os valores de lotes podem diminuir caso haja devoluções.
    </div>
    @endif
</div>
@endif

<div class="section">
    <div class="section-title"><span>PRODUTOS</span>Vendas por tipo de bilhete</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 28%;">Bilhete / Pacote</th>
                <th>Tipo</th>
                <th class="r">Preço</th>
                <th class="r">Vendidos</th>
                <th class="r">Lotação</th>
                <th style="width: 18%;">Ocupação</th>
                <th class="r">Receita</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ticketRows as [$ticket, $type])
            @php
                $capacity = (int) $ticket->max_qtd;
                $occupancy = $pct($ticket->stock_sells_count, $capacity);
                $soldOut = $capacity > 0 && $ticket->stock_sells_count >= $capacity;
            @endphp
            <tr>
                <td><strong>{{ $ticket->name }}</strong></td>
                <td class="muted">{{ $type }}</td>
                <td class="r">{{ $mt($ticket->price) }}</td>
                <td class="r">{{ $num($ticket->stock_sells_count) }}</td>
                <td class="r">{{ $capacity > 0 ? $num($capacity) : '—' }}</td>
                <td>
                    @if ($capacity > 0)
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 0; border: 0; background: none;"><div class="bar"><div style="width: {{ $soldOut ? 100 : $occupancy }}%;"></div></div></td>
                            <td style="padding: 0 0 0 6px; border: 0; background: none; width: 44px;" class="r">
                                @if ($soldOut)
                                    <strong>Esgotado</strong>
                                @else
                                    {{ $pctLabel($occupancy) }}
                                @endif
                            </td>
                        </tr>
                    </table>
                    @else
                    <span class="muted">—</span>
                    @endif
                </td>
                <td class="r">{{ $mt($revenue_by_ticket[$ticket->id] ?? 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="c muted">Sem bilhetes registados.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="r">{{ $num($ticketRows->sum(fn ($row) => $row[0]->stock_sells_count)) }}</td>
                <td></td>
                <td></td>
                <td class="r">{{ $mt($soldTotal) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

</body>
</html>
