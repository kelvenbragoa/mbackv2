<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carts;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;



class CartsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($userid){
        
        return response([
    
            'cart' => Carts::with('ticket:name,price,id')->where('protocol_id',$userid)->where('sell_id',null)->get()
    
        ],200);
    
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
    public function store(Request $request){

        $data = $request->all();
        $qtyToAdd = (int) ($data['qtd'] ?? 0);
        if ($qtyToAdd <= 0) {
            return response([
                'message' => 'Quantidade inválida.',
            ], 422);
        }

        $ticket = Ticket::find($data['ticket_id'] ?? null);
        if (! $ticket) {
            return response([
                'message' => 'Bilhete não encontrado.',
            ], 404);
        }

        $available = $ticket->availableQuantity();
        if ($available <= 0) {
            return response([
                'message' => 'Bilhete esgotado.',
            ], 409);
        }

        $existing = Carts::where('user_id', 0)
            ->where('protocol_id', $data['protocol_id'])
            ->where('ticket_id', $data['ticket_id'])
            ->whereNull('sell_id')
            ->first();
        $inCart = $existing ? (int) $existing->qtd : 0;
        $remaining = max(0, $available - $inCart);

        if ($qtyToAdd > $remaining) {
            return response([
                'message' => $remaining === 0
                    ? 'Já tens a quantidade máxima deste bilhete no carrinho.'
                    : 'Só restam '.$remaining.' bilhete(s) disponíveis.',
            ], 409);
        }

        if ($existing) {
            DB::table('carts')
              ->where('id', $existing->id)
              ->update(['qtd' => $inCart + $qtyToAdd]);

            return response([
                'message' => 'Foi acrescentada a quantidade do seu produto',
            ], 200);
        }

        Carts::create([
            'user_id' => 0,
            'protocol_id' => $data['protocol_id'],
            'ticket_id' => $data['ticket_id'],
            'event_id' => $data['event_id'],
            'qtd' => $qtyToAdd,
        ]);

        return response([
            'message' => 'Produto adicionado com sucesso',
        ], 200);
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
    public function destroy($id,$userid){
        $cart = Carts::find($id);

        if(!$cart)
        {
            return response([
                'message' => 'Produto não encontrado'
            ], 403);
        }

        if($cart->protocol_id != $userid)
        {
            return response([
                'message' => 'Permissão negada.'
            ], 403);

        }

      
        Carts::destroy($id);

        return response([

            'message' => 'Produto apagado'
        ], 200);
        
    }
}
