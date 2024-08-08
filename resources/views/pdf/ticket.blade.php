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

    @foreach ($detail as $item)
    <div class="ticket">
        <table width="95%">
            <tr>
                <td rowspan="3">
                    <div class="image">
                        <img width="240px" height="240px" src="https://backend.mticket.co.mz/storage/{{$event->image}}" alt="">
                    </div>
                 
                    
                </td>
                <td align="center" > 
                    <p class="date" style="justify-content: space-arround">
                    <span>{{date('l',strtotime($item->event->start_date))}}</span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span class="june-29">{{date('d-m',strtotime($item->event->start_date))}}</span>
                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span>{{date('Y',strtotime($item->event->start_date))}}</span>
                    </p>
                </td>
                <td class="show-name" align="center"><h2>{{$item->event->name}}</h2></td>
            </tr>
            <tr>
                
                <td align="center" class="show-name">
                    <h1>{{$item->event->name}}</h1>
                    <h2>{{$item->name}}</h2>
                    <h2>{{$item->ticket->name}}</h2>
                    <span class="card-ticket">{{$item->ticket->description}}</span>
                    <p class="time">{{date('H:i',strtotime($item->event->start_time))}}</p>

                </td>
                <td align="center">
                    <div class="time">
                        <p>{{date('H:i',strtotime($item->event->start_time))}}<span>ATÉ</span> {{date('H:i',strtotime($item->event->end_time))}}</p>
                        {{-- <p>DOORS <span>@</span> {{date('H:i',strtotime($item->event->start_time))}}</p> --}}
                    </div>
                    <div class="barcode">
                        @php
                            $myObj = new stdClass();
                            // $myObj->nome = Auth::user()->name;
                            // $myObj->email = Auth::user()->email;
                            // $myObj->evento = $event->name;
                            // $myObj->ticket = $item->ticket->name;
                            // $myObj->data = $event->start_date;
                            $myObj->s = $item->status;
                            $myObj->i = $item->id;
                            $myObj->ie = $item->event->id;
                            
    
                            $myJSON = json_encode($myObj);
                        @endphp
                        <img src="data:image/png;base64, {!! base64_encode(QrCode::format('svg')->size(100)->generate($myJSON)) !!}">
                        <p class="ticket-number2">
                            #0{{$item->id}}
                        </p>
                        {{-- {!!QrCode::generate($myJSON);!!} --}}
                    </div>
                </td>
            </tr>
           
           
         
            <tr>
                
                <td align="center">
                    <p class="location"><span>{{$item->event->address}},</span>
                        <span>{{$item->event->province->name}}, Moçambique</span>
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
    @endforeach



   
    
</body>
</html>


{{-- <!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Mticket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css">
    <link rel="stylesheet" href="./style.css"> 

</head>

<style>
/* @import url("https://fonts.googleapis.com/css2?family=Open+Sans&display=swap");
@import url("https://fonts.googleapis.com/css2?family=Staatliches&display=swap");
@import url("https://fonts.googleapis.com/css2?family=Nanum+Pen+Script&display=swap"); */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body,
html {
    height: 100vh;
    display: grid;
    font-family: "Staatliches", cursive;
    background: #ffff;
    color: black;
    font-size: 14px;
    letter-spacing: 0.1em;
    margin: 10px;
}

.ticket {
    margin: auto;
    display: flex;
    background: white;
    box-shadow: rgba(0, 0, 0, 0.3) 0px 19px 38px, rgba(0, 0, 0, 0.22) 0px 15px 12px;
}

.left {
    display: flex;
}

.image {
    height: 250px;
    width: 250px;
    background-image: url("/storage/{{$event->image}}");
    background-size: contain;
    opacity: 0.85;
}

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

.left .ticket-number {
    height: 250px;
    width: 250px;
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    padding: 5px;
}

.ticket-info {
    padding: 10px 30px;
    display: flex;
    flex-direction: column;
    text-align: center;
    justify-content: space-between;
    align-items: center;
}

.date {
    border-top: 1px solid gray;
    border-bottom: 1px solid gray;
    padding: 5px 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: space-around;
}

.date span {
    width: 100px;
}

.date span:first-child {
    text-align: left;
}

.date span:last-child {
    text-align: right;
}

.date .june-29 {
    color: #d83565;
    font-size: 20px;
}

.show-name {
    font-size: 20px;
    font-family: "Open Sans", cursive;
    color: #d83565;
}

.show-name h1 {
    font-size: 38px;
    font-weight: 700;
    letter-spacing: 0.1em;
    /* color: #04aff4; */
    color: black;
}

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
    font-size: 16px;
}

.location {
    display: flex;
    justify-content: space-around;
    align-items: center;
    width: 100%;
    padding-top: 8px;
    border-top: 1px solid gray;
}

.location .separator {
    font-size: 20px;
}

.right {
    width: 180px;
    border-left: 1px dashed #404040;
}

.right .admit-one {
    color: darkgray;
}

.right .admit-one span:nth-child(2) {
    color: gray;
}

.right .right-info-container {
    height: 250px;
    padding: 10px 10px 10px 35px;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    align-items: center;
}

.right .show-name h1 {
    font-size: 18px;
}

.barcode {
    height: 100px;
}

.barcode img {
    height: 100%;
}

.right .ticket-number {
    color: gray;
}

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
}
</style>

@php
    $i = 1;
@endphp

@foreach ($detail as $item)
    

<body>
    <!-- partial:index.partial.html -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />

    <div class="ticket">
        <div class="left">
            <div class="image">
                <p class="admit-one">
                    <span>Mticket</span>
                    <span>Mticket</span>
                    <span>Mticket</span>
                </p>
                <div class="ticket-number">
                    <p>
                        #0{{$item->id}}
                    </p>
                </div>
            </div>
            <div class="ticket-info">
                <p class="date">
                    <span>{{date('l',strtotime($item->event->start_date))}}</span>
                    <span class="june-29">{{date('d-m',strtotime($item->event->start_date))}}</span>
                    <span>{{date('Y',strtotime($item->event->start_date))}}</span>
                </p>
                <div class="show-name">
                    <h1>{{$item->event->name}}</h1>
                    <br>
                    <h2>{{Auth::user()->name}}</h2>
                    <h2>{{$item->ticket->name}}</h2>
                    <div class="cardticket">
                        <p>{{$item->ticket->description}}</p>
                    </div>
                    
                </div>
                <div class="time">

                    <p>{{date('H:i',strtotime($item->event->start_time))}}</p>
                </div>
                <p class="location"><span>{{$item->event->address}}</span>
                    <span class="separator"> 
                    @if ($item->status == 0)
                        <i class="fas fa-frown" style="color:red"></i>
                    @else
                        <i class="fas fa-smile" style="color:green"></i>
                    @endif  
                    </span><span>{{$item->event->province->name}}, Moçambique</span>
                </p>
            </div>
        </div>
        <div class="right">
            <p class="admit-one">
                <span>Mticket</span>
                <span>Mticket</span>
                <span>Mticket</span>
            </p>
            <div class="right-info-container">
                <div class="show-name">
                    <h1>{{$item->event->name}}</h1>

                </div>
                <div class="time">
                    <p>{{date('H:i',strtotime($item->event->start_time))}}<span>ATÉ</span> {{date('H:i',strtotime($item->event->end_time))}}</p>
                </div>
                <div class="barcode">
                    @php
                        $myObj = new stdClass();
                        
                        $myObj->s = $item->status;
                        $myObj->i = $item->id;
                        $myObj->ie = $item->event->id;
                        

                        $myJSON = json_encode($myObj);
                    @endphp
                    {!!QrCode::generate($myJSON);!!}
                </div>
                <p class="ticket-number">
                    #0{{$item->id}}
                </p>
            </div>
        </div>
    </div>
    <!-- partial -->
    <script src="./script.js"></script>

</body>

<br>

@php
    $i = $i + 1;
@endphp
@endforeach


</html> --}}