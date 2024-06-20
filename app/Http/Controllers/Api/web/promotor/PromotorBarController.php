<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\BarStore;
use App\Models\Products;
use Illuminate\Http\Request;

class PromotorBarController extends Controller
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

        BarStore::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $bar = BarStore::with('products')->find($id);
        return response()->json([
            "bar"=>$bar,
            "products"=>$bar->products
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $bar = BarStore::find($id);
        return response()->json(["bar"=>$bar]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $bar = BarStore::find($id);

        $bar->update($data);
        return response()->json($bar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $barstore = BarStore::findOrFail($id);

        $product = Products::where('bar_store_id',$barstore->id)->get();

        if(count($product) == 0){
            $barstore->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro");
        }
    }
    public function copy($id){
        $bar = BarStore::find($id);
        $newbar = BarStore::create([
            'name' => $bar->name.'-COPY',
            'event_id' => $bar->event_id,
        ]);

        foreach($bar->products as $product){
            Products::create([
                'name' => $product->name,
                'qtd' => $product->qtd,
                'sell_price' => $product->sell_price,
                'buy_price' => $product->buy_price,
                'event_id' => $product->event_id,
                'bar_store_id' => $newbar->id
            ]);
        }

        return response()->noContent();
    }
}
