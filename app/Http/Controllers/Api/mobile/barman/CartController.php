<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\CartBar;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index($userid){
        
        return response([
    
            'cart' => CartBar::with('product:name,sell_price,id')->where('user_id',$userid)->where('sell_id',null)->get()
    
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
        $product = Products::find($data['product_id']);
        if (CartBar::where('user_id',$data['user_id'] )->where('product_id',$data['product_id'])->where('sell_id',null)->where('event_id',$data['event_id'])->exists()) {

            if($data['qtd'] > $product->qtd){
                return response([
                    'message' => 'Produto Não adicionado. Estoque está baixo',
                    
                ], 200);
            }else{
                $rec_data = CartBar::where('user_id',$data['user_id'] )->where('product_id',$data['product_id'])->where('sell_id',null)->first();
                $qtd = $rec_data->qtd + $data['qtd'];
                // dd($qtd);
                //  update($qtd,$rec_data->id );
                DB::table('cart_bars')
                ->where('id', $rec_data->id )
                ->update(['qtd' => $qtd]);

                return response([
                    'message' => 'Foi acrescentada a quantidade do seu produto',
                    
                ], 200);
            }
        }else{


            if($data['qtd'] > $product->qtd){
                return response([
                    'message' => 'Produto Não adicionado. Estoque está baixo',
                    
                ], 200);
            }else{
            CartBar::create([
                'user_id' => $data['user_id'],
                'product_id' => $data['product_id'],
                'event_id' => $data['event_id'],
                'qtd' => $data['qtd'],
                
            ]);
    
            return response([
            'message' => 'Produto adicionado com sucesso',
            
        ], 200);
        }
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

    public function destroy($id,$userid){
        $cart = CartBar::find($id);

        if(!$cart)
        {
            return response([
                'message' => 'Produto não encontrado'
            ], 403);
        }

        if($cart->user_id != $userid)
        {
            return response([
                'message' => 'Permissão negada.'
            ], 403);

        }

      
        CartBar::destroy($id);

        return response([

            'message' => 'Produto apagado'
        ], 200);
        
    }
}
