<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Relatório</title>

    <style type="text/css">
        @page {
            margin: 0px;
        }
        html {
            margin-top: 30px ;
            
        
        }
        body {
            margin-top: 50px;
        }
        * {
            font-family: Verdana, Arial, sans-serif;
        }
        a {
            color: #fff;
            text-decoration: none;
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
            position:relative;
        }

        .informationbar {
            background-color: #1795ee;
            color: #FFF;
            position:relative;
        }
        .information .logo {
           
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
                <p>{{$event->name}}</p>
                <p>{{$event->user->name}}</p>
                <p>{{$event->user->email}}</p>


            </td>
            <td align="center">
                <img src="http://mticket.co.mz/template/logo1.png" alt="Logo" width="256" class="logo"/>
            </td> 
            <td align="right" style="width: 40%;">
                
                <h3>MTicket</h3>
                
                   <p> https://www.mticket.co.mz</p>
                   <p> +258 8500000</p>
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
    <h3 style="text-align:center" >Relatório das vendas bilhetes</h3>
    <h5><strong>Número de vendas fisicas</strong>: {{$tickets_local->sum('qty')}} ({{$tickets_local_amount}} MT)</h5>
    <h5><strong>Número de vendas online</strong>: {{$tickets_online->sum('qty')}} ({{$tickets_online->sum('total')}} MT)</h5>
    <h5><strong>Total Fisico e online</strong>: {{$tickets_local->sum('qty') + $tickets_online->sum('qty')}} ({{$tickets_local->sum('total') + $tickets_online->sum('total')}} MT)</h5>
    

    <hr>

    <h5><strong>Bilhetes Físicos</strong>: Validados: {{$tickets_local_false->count()}} , Não validados: {{$tickets_local_true->count()}}</h5>
    <h5><strong>Bilhetes Online</strong>: Validados: {{$tickets_online_false->count()}} , Não validados: {{$tickets_online_true->count()}}</h5>

    <br>

    <h2>Bilhetes Registrados</h2>
    <div>
        <table style="table-layout: fixed; width: 95%;">
            <thead>
                <tr>
                    <th  width="20%" align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        Ticket/Pacote
                    </th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        Vendas
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($event->tickets as $item)
                <tr>
                    <td style="border-top: 1px solid #eee; padding: 5px;">
                        {{$item->name}}
                    </td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        {{$item->sells->count()}}
                    </td>
                </tr>
                @endforeach
                
            </tbody>
        </table>
    </div>

    <hr>

    <h2>Venda Bilhetes</h2>
    <h5><strong>Número Bilhetes</strong>: {{$event->tickets->count()}}</h5>
    <h5><strong>Número Pacotes</strong>: {{$event->packages->count()}}</h5>
    <h5><strong>Valor</strong>: {{$event->sells->sum('total')}} MT</h5>
    <hr>
    <table style="table-layout: fixed; width: 95%;">
        <thead>
            <tr>
                <th  width="20%" align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    Bilhete
                </th>
               
                <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                   Preço
                </th>
               
                <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    Usuário/ID
                </th>
                <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    Data de venda
                </th>
                <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    Horas de venda
                </th>
               
               
            </tr>
        </thead>
        <tbody>
            @foreach ($event->sell_details as $item)
            <tr>
                <td style="border-top: 1px solid #eee; padding: 5px;">
                    {{$item->ticket->name}}
                </td>
               
                <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    {{$item->ticket->price}} MT
                </td>
               
                <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    {{$item->user->name ?? ''}}
                </td>
                <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    {{$item->created_at->format('d-m-Y')}}
                </td>
                <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                    {{$item->created_at->format('H:i')}}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    




   
   
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
{{-- 
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Relatório</title>

    <style type="text/css">
        @page {
            margin: 0px;
        }
        html {
            margin-top: 30px ;
            
        
        }
        body {
            margin-top: 50px;
        }
        * {
            font-family: Verdana, Arial, sans-serif;
        }
        a {
            color: #fff;
            text-decoration: none;
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
            position:relative;
        }

        .informationbar {
            background-color: #1795ee;
            color: #FFF;
            position:relative;
        }
        .information .logo {
           
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
                <p>{{$event->name}}</p>
                <p>{{$event->user->name}}</p>
                <p>{{$event->user->email}}</p>


            </td>
            <td align="center">
                <img src="http://mticket.co.mz/template/logo1.png" alt="Logo" width="256" class="logo"/>
            </td> 
            <td align="right" style="width: 40%;">
                
                <h3>MTicket</h3>
                
                   <p> https://www.mticket.co.mz</p>
                   <p> +258 8500000</p>
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
    <h3 style="text-align:center" >Relatório das vendas bilhetes</h3>
    <h5><strong>Número de vendas fisicas</strong>: 1399 (802682 MT)</h5>
    <h5><strong>Número de vendas online</strong>: 864 (439500 MT)</h5>
    <h5><strong>Convites Online</strong>: {{$invites_online->sum('qty')}} ({{$invites_online->sum('total')}} MT)</h5>
    <h5><strong>Total Fisico e online</strong>: {{$tickets_local->sum('qty') + $tickets_online->sum('qty')}} ({{$tickets_local->sum('total') + $tickets_online->sum('total')}} MT)</h5>
    

    <hr>

    <h5><strong>Bilhetes Físicos</strong>: Validados: 1100 , Não validados: 299</h5>
    <h5><strong>Bilhetes Online</strong>: Validados: 858 , Não validados: 6</h5>
    <h5><strong>Convites Online</strong>: Validados: {{$invites_online_false->count()}} , Não validados: {{$invites_online_true->count()}}</h5>

    <br>

    <h2>Bilhetes Registrados</h2>
    <div>
        <table style="table-layout: fixed; width: 95%;">
            <thead>
                <tr>
                    <th  width="20%" align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        Ticket/Pacote
                    </th>
                    <th align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        Vendas
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($event->tickets as $item)
                <tr>
                    <td style="border-top: 1px solid #eee; padding: 5px;">
                        {{$item->name}}
                    </td>
                    <td align="left" style="border-top: 1px solid #eee; padding: 5px;">
                        {{$item->sells->count()}}
                    </td>
                </tr>
                @endforeach
                
            </tbody>
        </table>
    </div>

    <hr>

    <h2>Venda Bilhetes</h2>
    <h5><strong>Número Bilhetes</strong>: {{$event->tickets->count()}}</h5>
    <h5><strong>Número Pacotes</strong>: {{$event->packages->count()}}</h5>
    <h5><strong>Valor</strong>: {{$event->sells->sum('total')}} MT</h5>
    




   
   
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
</html> --}}