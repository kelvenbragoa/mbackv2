<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Http\Controllers\Controller;
use App\Mail\SendTickets;
use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\TemporarySell;
use App\Models\TemporarySellDetails;
use App\Models\TemporaryTransaction;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Notifications\TicketPaid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Twilio\Rest\Client;

class ClientCheckOutController extends Controller
{
        public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $config = \abdulmueid\mpesa\Config::loadFromFile('config.php');
        $transactionmpesa = new \abdulmueid\mpesa\Transaction($config);
        $data = $request->all();


        $event = Event::find($data['tickets'][0]['event_id']);


        $order = [];
        $string = substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(3/strlen($x)) )),1,4);
        $transaction = Transaction::orderBy('id','desc')->first();
        $add = $transaction->id ?? 0 + 1;
        $ref = 'MT'.$add.'T'.$string.$add;
        $amount= $data['amount'];
        

        if($event->type_event_id == 1){
            $request->validate([
                'paymentNumber'=>'required|string',
            ]);
            $msisdn = $data['paymentNumber'];
            foreach($data['tickets'] as $item){
                if($item['quantity']>0){
                    $temporarySell = TemporarySell::create([
                        'event_id'=>$item['event_id'],
                        'ticket_id'=>$item['id'],
                        'qty'=>$item['quantity'],
                        'price'=>$item['price'],
                        'total'=>$item['price']*$item['quantity'],
                        'status'=>0,
                        'name'=>$data['customerName'],
                        'email'=>$data['customerEmail'],
                        'mobile'=>$data['customerMobile'],
                        'user_id'=>Auth::user()->id ?? null,
                    ]);
                    TemporaryTransaction::create([
                        'sell_id'=>$temporarySell->id,
                        'reference'=>$ref,
                        'method'=>'mpesa',
                        'status'=>0,
                        'user_id'=>Auth::user()->id ?? null,

                    ]);
                    for ($i=0; $i < $item['quantity']; $i++) { 
                        TemporarySellDetails::create([
                            'sell_id'=>$temporarySell->id,
                            'event_id'=>$item['event_id'],
                            'ticket_id'=>$item['id'],
                            'status'=>0,
                            'name'=>$data['customerName'],
                            'email'=>$data['customerEmail'],
                            'mobile'=>$data['customerMobile'],
                            'user_id'=>Auth::user()->id ?? null,

                        ]);
                    }
                }
            }

            try {
                $c2b = $transactionmpesa->c2b(
                    $amount, //valor a cobrar do cliente
                    // 1, //valor a cobrar do cliente
                    $msisdn, // número de telefone do cliente vodacom com mpesa registrado
                    $ref, //referencia do pagamento
                    $ref //referencia do pagamento
                );
                if($c2b->getCode() === 'INS-0') { //codigo de sucesso de pagamento
                    foreach($data['tickets'] as $item){
                        if($item['quantity']>0){
                            $sell = Sell::create([
                                'event_id'=>$item['event_id'],
                                'ticket_id'=>$item['id'],
                                'qty'=>$item['quantity'],
                                'price'=>$item['price'],
                                'total'=>$item['price']*$item['quantity'],
                                'status'=>1,
                                'name'=>$data['customerName'],
                                'email'=>$data['customerEmail'],
                                'mobile'=>$data['customerMobile'],
                                'user_id'=>Auth::user()->id ?? null,
        
                            ]);
                            Transaction::create([
                                'sell_id'=>$sell->id,
                                'reference'=>$ref,
                                'method'=>'mpesa',
                                'user_id'=>Auth::user()->id ?? null,
        
                            ]);
                            
                            for ($i=0; $i < $item['quantity']; $i++) { 
                                SellDetails::create([
                                    'sell_id'=>$sell->id,
                                    'event_id'=>$item['event_id'],
                                    'ticket_id'=>$item['id'],
                                    'status'=>1,
                                    'name'=>$data['customerName'],
                                    'email'=>$data['customerEmail'],
                                    'mobile'=>$data['customerMobile'],
                                    'user_id'=>Auth::user()->id ?? null,
        
                                ]);
                            }
                            $sell->load('selldetails');
                            $sell->load('ticket');
                            $sell->load('event.province');
        
        
                            $order[]=$sell;
                        }
                        
                    }

                    $temporarySell->transaction()->delete();
                    $temporarySell->selldetails()->delete();
                    $temporarySell->delete();

                    $msg_content = "Olá, {$data['customerName']}. A sua compra para o evento {$event->name} foi realizada com sucesso. Segue o seu bilhete em anexo.";
                    $detail = SellDetails::where('sell_id',$sell->id)->get();

                    try {
                        Mail::to($data['customerEmail'])->send(new SendTickets($detail,$event->id,$sell->id,$msg_content));
                        $this->sendwhatsapp($data['customerMobile'],$sell->id);
                        // $this->sendtwilio($data['customerMobile'],$sell->id);
                            } catch (\Throwable $th) {
                                
                            }
                    

                    return response()->json([
                        'order'=>$order,
                    ]);
                }
                if($c2b->getCode() === 'INS-1') {
        
                    return abort(404,'Erro interno, volte a tentar novamente');
            
                }
            
                if($c2b->getCode() === 'INS-2') {
                    //API INVALIDA
                    return abort(404,'Erro interno, volte a tentar novamente');
            
                }
            
                if($c2b->getCode() === 'INS-4') {
                    //API INVALIDA, USUARIO NAO ATIVO
                    return abort(404,'Erro interno, volte a tentar novamente');
            
                }
            
                if($c2b->getCode() === 'INS-5') {
                    //API INVALIDA, USUARIO CANCELOU
                    return abort(404,'Transação cancelado pelo usuário');
            
                }
            
                if($c2b->getCode() === 'INS-6') {
                    //API INVALIDA, Transaçãp falhou
                    return abort(404,'Transação falhou');
            
                }
            
                if($c2b->getCode() === 'INS-9') {
                    //API INVALIDA, REQUEST TIMEOUT
                    return abort(404,'O tempo expirou. Volte a tentar');
            
                }
            
                if($c2b->getCode() === 'INS-10') {
                
                    return abort(404,'Transação duplicada');
            
                }
                if($c2b->getCode() === 'INS-16') {
                
                    return abort(404,'Erro interno volte mais tarde');
            
                }
            
                if($c2b->getCode() === 'INS-2006') {
                
                    return abort(404,'Saldo insuficiente');
            
                }
            
                if($c2b->getCode() === 'INS-2051') {
                
                    return abort(404,'Número de telefone inválido');
            
                }        
            } catch (\Throwable $th) {
                return abort(404,$th->getMessage());      
            }
    }else{

        foreach($data['tickets'] as $item){
            if($item['quantity']>0){
                $sell = Sell::create([
                    'event_id'=>$item['event_id'],
                    'ticket_id'=>$item['id'],
                    'qty'=>$item['quantity'],
                    'price'=>$item['price'],
                    'total'=>$item['price']*$item['quantity'],
                    'status'=>1,
                    'name'=>$data['customerName'],
                    'email'=>$data['customerEmail'],
                    'mobile'=>$data['customerMobile'],
                    'user_id'=>Auth::user()->id ?? null,

                ]);
                Transaction::create([
                    'sell_id'=>$sell->id,
                    'reference'=>$ref,
                    'method'=>'interno',
                    'user_id'=>Auth::user()->id ?? null,

                ]);
                
                for ($i=0; $i < $item['quantity']; $i++) { 
                    SellDetails::create([
                        'sell_id'=>$sell->id,
                        'event_id'=>$item['event_id'],
                        'ticket_id'=>$item['id'],
                        'status'=>1,
                        'name'=>$data['customerName'],
                        'email'=>$data['customerEmail'],
                        'mobile'=>$data['customerMobile'],
                        'user_id'=>Auth::user()->id ?? null,

                    ]);
                }
                $sell->load('selldetails');
                $sell->load('ticket');
                $sell->load('event.province');


                $order[]=$sell;
            }
            
        }

        $msg_content = "Olá, {$data['customerName']}. A sua compra para o evento {$event->name} foi realizada com sucesso. Segue o seu bilhete em anexo.";
                    $detail = SellDetails::where('sell_id',$sell->id)->get();

                    try {
                        Mail::to($data['customerEmail'])->send(new SendTickets($detail,$event->id,$sell->id,$msg_content));
                        $this->sendwhatsapp($data['customerMobile'],$sell->id);
                            } catch (\Throwable $th) {
                                
                            }
        return response()->json([
            'order'=>$order,
        ]);

    }

    
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $event = Event::with('user')->with('province')->with('city')->with('tickets')->with('like')->with('lineups')->with('type')->find($id);
        $ticket = Ticket::where('event_id', $event->id)->get();

        $ticket->transform(function ($item){
            $item->quantity = 0;
              return $item;
        });

        return response()->json([
            "events"=>$event,
            "tickets"=>$ticket,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function sendwhatsapp($number,$sell_id){
        try {
            $url = $this->ticketdownload($sell_id);
            $ticket = new TicketPaid($url,$sell_id,$number);
            Notification::send($number,$ticket);
        } catch (Exception $e) {
            return $e;
        }
    }

    public function sendtwilio($number,$sell_id)
    {
        $url = $this->ticketdownload($sell_id);
        $receiverNumber = 'whatsapp:+258'.$number; // Replace with the recipient's phone number
        $message = 'Obrigado pela sua compra. O bilhete encontra-se em anexo. Este bilhete é intransmissivel. Para suporte contacte o seguinte email: suporte@mticket.co.mz'; // Replace with your desired message
        // $mediaUrl = 'https://mticket.co.mz/demo/images/logo2.png'; // Replace with the media URL
        // $mediaUrl = 'https://inogest-atas.s3.amazonaws.com/meeting-attachment/qPBGAXU72RPO9m5M2VuS4jFDjO1yHhIiuvYUtQfP.pdf?response-content-disposition=attachment&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAV2FXJ6Y2RJHFRKWL%2F20240809%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240809T054758Z&X-Amz-SignedHeaders=host&X-Amz-Expires=600&X-Amz-Signature=0e8696f1c8dd8c6c8595848645ebf6ce79476b389d5ab514fa1ffd1357c81de0';
        // $mediaUrl = 'https://backend.mticket.co.mz/ticketdownload/8';
        $mediaUrl = $url;
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_NUMBER');

        try {
            $client = new Client($sid, $token);
            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message,
                'mediaUrl'=>$mediaUrl
            ]);

            
        } catch (Exception $e) {
            
        }
    }

    public function ticketdownload($id){

        $sell = Sell::find($id);
        $detail = SellDetails::where('sell_id',$id)->get();
        $event = Event::find($sell->event_id);


        $pdf = PDF::loadView('pdf.ticket', compact('detail','event'));
        $fileName = 'ticket-'.$id.'.pdf';
        $pdf->save(storage_path('app/public/tickets/'.$fileName));

        return 'https://backend.mticket.co.mz/storage/tickets/ticket-'.$id.'.pdf';

    }
}
