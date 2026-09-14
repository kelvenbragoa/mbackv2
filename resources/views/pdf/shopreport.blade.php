<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório da loja</title>

    <style type="text/css">
        @page {
            margin: 0px;
        }
        html {
            margin-top: 30px;
        }
        body {
            margin-top: 50px;
        }
        * {
            font-family: Verdana, Arial, sans-serif;
        }
        table {
            font-size: x-small;
        }
        tfoot tr td {
            font-weight: bold;
            font-size: x-small;
        }
        .invoice table {
            margin: 15px;
        }
        .invoice h3 {
            margin-left: 15px;
        }
        .invoice h2 {
            margin-left: 15px;
        }
        .invoice h5 {
            margin-left: 15px;
        }
        .information p {
            color: rgb(255, 255, 255);
        }
        .information {
            background-color: #1795ee;
            color: #FFF;
            position: relative;
        }
        .informationbar {
            background-color: #1795ee;
            color: #FFF;
            position: relative;
        }
        .information table {
            padding: 15px;
        }
    </style>
</head>
<body>

<div class="information" style="width:100%; position: absolute; top: -50;">
    <table width="100%">
        <tr>
            <td align="left" style="width: 40%;">
                <p><strong> Evento</strong></p>
                <p>{{ $event->name }}</p>
                <p>{{ $event->user->name ?? '' }}</p>
                <p>{{ $event->user->email ?? '' }}</p>
            </td>
            <td align="center">
                <img src="https://mticket.co.mz/demo/images/logo2.png" alt="Logo" width="256" class="logo"/>
            </td>
            <td align="right" style="width: 40%;">
                <h3>MTicket</h3>
                <p> https://www.mticket.co.mz</p>
                <p> +258 84 228 0974</p>
                <p> Beira, Mozambique</p>
                <p> ConnectUs LTD</p>
            </td>
        </tr>
    </table>
</div>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br/>

<div class="invoice">
    <h3 style="text-align:center">Relatório de vendas da loja do evento</h3>
    <h5><strong>Produtos no catálogo</strong>: {{ $totals['products'] }}</h5>
    <h5><strong>Encomendas pagas</strong>: {{ $totals['orders'] }}</h5>
    <h5><strong>Quantidade vendida</strong>: {{ $totals['qty'] }}</h5>
    <h5><strong>Receita</strong>: {{ number_format($totals['revenue'], 2, ',', ' ') }} MT</h5>
    <h5><strong>Levantadas</strong>: {{ $totals['picked_up'] }}</h5>
    <h5><strong>Por levantar</strong>: {{ $totals['pending'] }}</h5>

    <hr>
    <br>

    <h2>Produtos</h2>
    <div>
        <table style="table-layout: fixed; width: 95%;">
            <thead>
                <tr>
                    <th width="30%" align="left" style="border-top: 1px solid #eee; padding: 5px;">Nome</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Stock atual</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Qtd vendida</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Valor de venda</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($productRows as $item)
                <tr>
                    <td style="border-top: 1px solid #eee; padding: 5px;">{{ $item['name'] }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ $item['stock'] }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ $item['sold_qtd'] }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ number_format($item['sold_value'], 2, ',', ' ') }} MT</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <hr>

    <h2>Encomendas pagas</h2>
    <h2>Número de encomendas: {{ $orders->count() }}</h2>
    <div>
        <table style="table-layout: fixed; width: 95%;">
            <thead>
                <tr>
                    <th width="16%" align="left" style="border-top: 1px solid #eee; padding: 5px;">Data</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Recibo</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Cliente</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Produtos</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Valor</th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">Levantamento</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $item)
                <tr>
                    <td style="border-top: 1px solid #eee; padding: 5px;">{{ optional($item->created_at)->format('d-M H:i') }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ $item->qrcode }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ $item->name }} {{ $item->mobile }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ $item->details->pluck('product_name')->implode(', ') }}</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">{{ number_format($item->total, 2, ',', ' ') }} MT</td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        @if ($item->picked_up_at)
                            Levantado {{ $item->picked_up_at->format('d-M H:i') }}
                        @else
                            Pendente
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="informationbar" style="width:100%; position: absolute; bottom: 0;">
    <table width="100%">
        <tr>
            <td align="left" style="width: 50%;">
                &copy; {{ date('Y') }} Mticket. Todos direitos reservado.
            </td>
            <td align="right" style="width: 60%;">
                ConnectUs LTD, Mozambique
            </td>
        </tr>
    </table>
</div>
</body>
</html>
