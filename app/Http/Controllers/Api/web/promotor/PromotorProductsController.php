<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\BarStore;
use App\Models\Products;
use App\Models\SellBar;
use App\Models\SellDetailBar;
use App\Models\SellDetails;
use Illuminate\Http\Request;

class PromotorProductsController extends Controller
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
    public function create($id)
    {
        //
        $bar = BarStore::where('event_id',$id)->get();
        return response()->json([
            "bar" => $bar,

        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'buy_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'event_id' => ['required', 'integer'],
            'bar_store_id' => ['required', 'integer'],
        ]);

        // Stock starts at 0; use stock notes (entrada) to add quantity.
        $data['qtd'] = 0;

        $product = Products::create($data);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $product = Products::with('barstore')->find($id);
        $bar = BarStore::where('event_id',$product->event_id)->get();
        return response()->json([
            "product" => $product,
            "bar" => $bar,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $product = Products::with('barstore')->find($id);
        $bar = BarStore::where('event_id',$product->event_id)->get();
        return response()->json([
            "product" => $product,
            "bar" => $bar,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $produto = Products::find($id);
        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'buy_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sell_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'bar_store_id' => ['sometimes', 'required', 'integer'],
            'event_id' => ['sometimes', 'required', 'integer'],
        ]);

        // Intentionally ignore qtd — stock changes only via stock notes.
        $produto->update($data);

        return response()->json($produto);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $product = Products::findOrFail($id);

        $sellbar = SellDetailBar::where('product_id',$product->id)->get();

        if(count($sellbar) == 0){
            $product->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro");
        }
    }
}
