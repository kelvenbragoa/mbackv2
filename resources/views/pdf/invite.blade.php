<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Bilhete</title>
    {{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" /> --}}
    <style>
        @page {
            margin: 0px;
        }
        .break-page{
          page-break-after: always;
        }

        html {
            margin: 70px ;
            color: black;
            font-size: 10px;
            letter-spacing: 0.1em;
            margin: 10px;
            font-family: "Staatliches", cursive;
            background: #ffffff;
        
        }

        .ticket {
            background: #f3f3f3;
        },
        .image {
        height: 250px;
        width: 250px;
        background-image: url("https://backend.mticket.co.mz/storage/{{$event->image}}");
        background-size: contain;
        opacity: 0.85;
    },
    .date {
        border-top: 1px solid gray;
        border-bottom: 1px solid gray;
        padding: 5px 0;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: space-around;
    },
    .date .june-29 {
        color: #d83565;
        font-size: 12px;
    },
    .show-name {
        font-size: 10px;
        font-family: "Open Sans", cursive;
        color: #000000;
    },
    .show-name h1 {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.1em;
        /* color: #04aff4; */
        color: black;
    },
    .cardticket {
       
       align-items: center;
       padding: 10px 30px;
       display: flex;
       flex-direction: column;
       text-align: center;
       justify-content: space-between;
       align-items: center;
       max-width: 50ch;
   
   }
   
   .cardticket p {
       color: black
   },
   .time {
        padding: 10px 0;
        /* color: #04aff4; */
        color: black;
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: 10px;
        font-weight: 700;
    }
    
    .time span {
        font-weight: 400;
        color: gray;
    }
    
    .left .time {
        font-size: 12px;
    },
    .location {
        display: flex;
        justify-content: space-around;
        align-items: center;
        width: 100%;
        padding-top: 8px;
        border-top: 1px solid gray;
    }
    
    .location .separator {
        font-size: 12px;
    },
    .barcode {
        height: 100px;
    }
    
    .barcode img {
        height: 100%;
    },
    .ticket-number2 {
        color: gray;
    },
    .ticket-number1 {
        height: 250px;
        width: 250px;
        display: flex;
        justify-content: flex-end;
        align-items: flex-end;
        padding: 5px;
    },
    .admit-one {
        position: absolute;
        color: darkgray;
        height: 250px;
        padding: 0 10px;
        letter-spacing: 0.15em;
        display: flex;
        text-align: center;
        justify-content: space-around;
        writing-mode: vertical-rl;
        transform: rotate(-180deg);
    }
    
    .admit-one span:nth-child(2) {
        color: white;
        font-weight: 700;
    }
    
   
 
    </style>
</head>
<body>


    @php
    $i = 1;
    @endphp

    <div class="ticket">
        <table width="95%">
            <tr>
                <td rowspan="3">
                    <div class="image">
                        <img width="240px" height="240px" src="https://backend.mticket.co.mz/storage/{{$customer->event->image}}" alt="">
                    </div>
                 
                    
                </td>
                <td align="center" > 
                    <p class="date" style="justify-content: space-arround">
                    <span>{{date('l',strtotime($customer->event->start_date))}}</span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span class="june-29">{{date('d-m',strtotime($customer->event->start_date))}}</span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span>{{date('Y',strtotime($customer->event->start_date))}}</span>
                    </p>
                </td>
                <td class="show-name" align="center"><h2>{{$customer->event->name}}</h2></td>
            </tr>
            <tr>
                
                <td align="center" class="show-name">
                    <h1>{{$customer->event->name}}</h1>
                    <h2>{{$customer->name}}</h2>
                    <h2>{{$customer->invite->name}}</h2>
                    <span class="card-ticket">{{$customer->invite->description}}</span>
                    <p class="time">{{date('H:i',strtotime($customer->event->start_time))}}</p>

                </td>
                <td align="center">
                    <div class="time">
                        <p>{{date('H:i',strtotime($customer->event->start_time))}}<span>ATÉ</span> {{date('H:i',strtotime($customer->event->end_time))}}</p>
                        {{-- <p>DOORS <span>@</span> {{date('H:i',strtotime($customer->event->start_time))}}</p> --}}
                    </div>
                    <div class="barcode">
                        @php
                            $myObj = new stdClass();
                            // $myObj->nome = Auth::user()->name;
                            // $myObj->email = Auth::user()->email;
                            // $myObj->evento = $event->name;
                            // $myObj->ticket = $customer->invite->name;
                            // $myObj->data = $event->start_date;
                            $myObj->s = $customer->status;
                            $myObj->c = $customer->id;
                            $myObj->ie = $customer->event->id;
                            
    
                            $myJSON = json_encode($myObj);
                        @endphp
                        <img src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(100)->generate($myJSON)) !!}">
                        <p class="ticket-number2">
                            #0{{$customer->id}}
                        </p>
                        {{-- {!!QrCode::generate($myJSON);!!} --}}
                    </div>
                </td>
            </tr>
           
           
         
            <tr>
                
                <td align="center">
                    <p class="location"><span>{{$customer->event->address}},</span>
                        <span>{{$customer->event->province->name}}, Moçambique</span>
                    </p>
                </td>
                
            </tr>
        </table>
        
    </div>
    <br>
    <br>
    <br>
    @php
    $i = $i + 1;
    @endphp



   
    
</body>
</html>
