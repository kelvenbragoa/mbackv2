<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Models\CardTransaction;
use App\Models\EventCard;
use App\Models\Transaction;
use Illuminate\Http\Request;

class UserCashlessController extends Controller
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
        $data = $request->all();
        $card = EventCard::where('id', $data['card'])->first();
        if ($card == null) {
            return response()->json([
                "message" => "Pulseira nao encontrada",
            ], 404);
        }
        if($card->status == 0){
            return response()->json([
                "message" => "Pulseira desativada, por favor contacte a mticket",
            ], 404);
        }

        return response()->json([
            "card"=>$card,
            "message"=>"Pulseira encontrada com sucesso"
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $card = EventCard::find($id);
        if($card->status == 0){
            return response()->json([
                "message" => "Pulseira desativada, por favor contacte a mticket",
            ], 404);
        }
        $transactions = CardTransaction::where('card_id',$id)->orderby('id','desc')->get();

        return response()->json([
            'card'=>$card,
            'transactions'=>$transactions,
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

    public function recharge(Request $request){
        $config = \abdulmueid\mpesa\Config::loadFromFile('config.php');
        $transactionmpesa = new \abdulmueid\mpesa\Transaction($config);
        $data = $request->all();
        $card = EventCard::find($data['cardId']);
        $transactions = CardTransaction::where('card_id',$data['cardId'])->orderby('id','desc')->get();

        $string = substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(3/strlen($x)) )),1,4);
        $transaction = Transaction::orderBy('id','desc')->first();
        $add = $transaction->id + 1;
        $ref = 'MTC'.$card->id.'T'.$string.$add;
        $amount= $data['amount'];
        $msisdn = $data['paymentNumber'];

        $c2b = $transactionmpesa->c2b(
            1, //valor a cobrar do cliente
            $msisdn, // número de telefone do cliente vodacom com mpesa registrado
            $ref, //referencia do pagamento
            $ref //referencia do pagamento
        );
        $newBalance = $card->balance + $amount;
        if($c2b->getCode() === 'INS-0') {
            CardTransaction::create([
                'card_id'=>$card->id,
                'event_card_id'=>$card->id,
                'event_id'=>$card->event_id,
                'sell_id'=>0,
                'total'=>$amount,
                'balance'=>$newBalance,
                'type_of_transaction_id'=>0,
            ]);
    
            $card->update([
                'balance'=>$newBalance,
                'status'=>1,
            ]);
            return response()->json([
                'card'=>EventCard::find($data['cardId']),
                'transactions'=>CardTransaction::where('card_id',$data['cardId'])->orderby('id','desc')->get(),
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

        

    }
}
