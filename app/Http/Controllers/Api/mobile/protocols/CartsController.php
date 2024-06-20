<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carts;
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

        if (Carts::where('user_id',0)->where('protocol_id',$data['protocol_id'])->where('ticket_id',$data['ticket_id'])->where('sell_id',null)->exists()) {
            $rec_data = Carts::where('user_id',0)->where('protocol_id',$data['protocol_id'])->where('ticket_id',$data['ticket_id'])->where('sell_id',null)->first();
            $qtd = $rec_data->qtd + $data['qtd'];
            // dd($qtd);
            //  update($qtd,$rec_data->id );
            DB::table('carts')
              ->where('id', $rec_data->id )
              ->update(['qtd' => $qtd]);
            //   return back()->with('message','Foi aumentado a quantidade do produto, Clique para verificar');
            return response([
                'message' => 'Foi acrescentada a quantidade do seu produto',
                
            ], 200);
        }else{
            
            Carts::create([
                'user_id' => 0,
                'protocol_id' => $data['protocol_id'],
                'ticket_id' => $data['ticket_id'],
                'event_id' => $data['event_id'],
                'qtd' => $data['qtd'],
                
            ]);

            return response([
                'message' => 'Produto adicionado com sucesso',
                
            ], 200);
        
    
            // return back()->with('message','Bilhete adicionado ao carrinho, Clique para verificar');
        }
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
