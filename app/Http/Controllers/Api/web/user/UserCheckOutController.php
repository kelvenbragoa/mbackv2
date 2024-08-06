<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
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

class UserCheckOutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
                        'user_id'=>$data['user_id'] ?? null,
                    ]);
                    TemporaryTransaction::create([
                        'sell_id'=>$temporarySell->id,
                        'reference'=>$ref,
                        'method'=>'mpesa',
                        'status'=>0,
                        'user_id'=>$data['user_id'] ?? null,

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
                            'user_id'=>$data['user_id'] ?? null,

                        ]);
                    }
                }
            }

            try {
                $c2b = $transactionmpesa->c2b(
                    $amount, //valor a cobrar do cliente
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
                                'user_id'=>$data['user_id'] ?? null,
        
                            ]);
                            Transaction::create([
                                'sell_id'=>$sell->id,
                                'reference'=>$ref,
                                'method'=>'mpesa',
                                'user_id'=>$data['user_id'] ?? null,
        
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
                                    'user_id'=>$data['user_id'] ?? null,
        
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
                    'user_id'=>$data['user_id'] ?? null,

                ]);
                Transaction::create([
                    'sell_id'=>$sell->id,
                    'reference'=>$ref,
                    'method'=>'interno',
                    'user_id'=>$data['user_id'] ?? null,

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
                        'user_id'=>$data['user_id'] ?? null,

                    ]);
                }
                $sell->load('selldetails');
                $sell->load('ticket');
                $sell->load('event.province');


                $order[]=$sell;
            }
            
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
}
