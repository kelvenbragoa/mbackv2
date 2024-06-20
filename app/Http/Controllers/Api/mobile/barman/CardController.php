<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\CardTransaction;
use App\Models\EventCard;
use App\Models\Refund;
use Illuminate\Http\Request;

class CardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function refund($id,$userid){
        $card = EventCard::with('user')->find($id);

        $refund = Refund::where('event_card_id',$id)->first();

        $balance = $card->balance;

        $card_transaction = CardTransaction::create([
            'card_id'=>$card->id,
            'event_card_id'=>$card->id,
            'event_id'=>$card->event_id,
            'sell_id'=>0,
            'total'=>$balance,
            'balance'=>0,
            'type_of_transaction_id'=>2,
            'user_id'=>$userid,
        ]);

        $card->update([
            'balance'=>0,
            'status'=>0,

        ]);

        $refund->update([
            'status'=>1
        ]);

        return response([
            'card' => [$card],
            'message'=>'Devolução feita com sucesso',
        ],200);

    }

    public function registerCard($id,$userid){

        $card = EventCard::create([
            'name'=>'USER',
            'event_id'=>$id,
            'status'=>0,
            'card_id'=>0,
            'balance'=>0,
            'user_id'=>$userid,
        ]);

        $newcard = EventCard::with('user')->find($card->id);

        


        return response([
            'card' => [$newcard],
        ],200);

        
    }

    public function viewCard($id){

        $card = EventCard::where('id',$id)->with('user')->get();
        $transactions = CardTransaction::where('card_id',$id)->orderby('id','desc')->get();

        return response([
            'card' => $card,
            'transactions' => $transactions
        ],200);
    }

    public function topUpCard($id,$top,$userid){

        $card = EventCard::with('user')->find($id);

        
        $newBalance = $card->balance + $top;

        // $second_transaction = CardTransaction::where('event_card_id',$id)->orderBy('id','desc')->skip(1)->first();
        $first_transaction = CardTransaction::where('event_card_id',$id)->orderBy('id','desc')->first();

        if($first_transaction == null){

            $card_transaction = CardTransaction::create([
                'card_id'=>$card->id,
                'event_card_id'=>$card->id,
                'event_id'=>$card->event_id,
                'sell_id'=>0,
                'total'=>$top,
                'balance'=>$newBalance-100,
                'type_of_transaction_id'=>0,
                'user_id'=>$userid,
            ]);
    
            $card->update([
                'balance'=>$newBalance-100,
                'status'=>1,
            ]);

            Refund::create([
                'user_id'=>$userid,
                'event_card_id'=>$card->id,
                'event_id'=>$card->event_id,
                'card_transaction_id'=>$card_transaction->id,
                'total'=>$top,
                'status'=>0,
                'refund'=>100,
                'balance'=>$newBalance-100,
            ]);
    
            $newcard = EventCard::with('user')->find($id);

            $refund = $newBalance - 100;
    
            
    
    
            return response([
                'card' => [$newcard],
                'message'=>'Cartão recarregado com '.$top.'MT. Saldo atual '.$refund.'MT. Taxa de caução 100 MT',
            ],200);

        }else{

            if($card->status == 0){
                return response([
                    'card' => [$card],
                    'message'=>'Não foi possível carregar porque o cartão já foi devolvido',
                ],200);
            }

            if($first_transaction->total == $top && $first_transaction->type_of_transaction_id == 0){
                $seconds = now()->diffInSeconds($first_transaction->created_at);
                if($seconds > 15){

                    CardTransaction::create([
                        'card_id'=>$card->id,
                        'event_card_id'=>$card->id,
                        'event_id'=>$card->event_id,
                        'sell_id'=>0,
                        'total'=>$top,
                        'balance'=>$newBalance,
                        'type_of_transaction_id'=>0,
                        'user_id'=>$userid,
                    ]);

            
                    $card->update([
                        'balance'=>$newBalance,
                        'status'=>1,
                    ]);
            
                    $newcard = EventCard::with('user')->find($id);

                    return response([
                        'card' => [$newcard],
                        'message'=>'Cartão recarregado com '.$top.'MT. Saldo atual '.$newBalance.'MT',
                    ],200);

                }else{

                    $newcard = EventCard::with('user')->find($id);
                    return response([
                        'card' => [$newcard],
                        'message'=>'Erro. Parece que houve uma duplicação de recarregamento. Aguarde um momento e tente novamente'
                        
                    ], 200);
                }
            }else{

                CardTransaction::create([
                    'card_id'=>$card->id,
                    'event_card_id'=>$card->id,
                    'event_id'=>$card->event_id,
                    'sell_id'=>0,
                    'total'=>$top,
                    'balance'=>$newBalance,
                    'type_of_transaction_id'=>0,
                    'user_id'=>$userid,
                ]);
        
                $card->update([
                    'balance'=>$newBalance,
                    'status'=>1,
                ]);
        
                $newcard = EventCard::with('user')->find($id);
        
                
        
        
                return response([
                    'card' => [$newcard],
                    'message'=>'Cartão recarregado com '.$top.'MT. Saldo atual '.$newBalance.'MT',
                ],200);
            }
    }
}


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
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
