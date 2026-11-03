<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\BarStore;
use App\Models\Products;
use Illuminate\Http\Request;

class PromotorBarController extends Controller
{
    use AuthorizesEventAccess;

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

        if ($denied = $this->denyEventAccess($data['event_id'] ?? null)) {
            return $denied;
        }

        BarStore::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $bar = $this->findBar($id);
        if (! $bar instanceof BarStore) {
            return $bar;
        }

        $bar->load('products');
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
        $bar = $this->findBar($id);
        if (! $bar instanceof BarStore) {
            return $bar;
        }

        return response()->json(["bar"=>$bar]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $bar = $this->findBar($id);
        if (! $bar instanceof BarStore) {
            return $bar;
        }

        $data = $request->except('event_id');

        $bar->update($data);
        return response()->json($bar);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $barstore = $this->findBar($id);
        if (! $barstore instanceof BarStore) {
            return $barstore;
        }

        $product = Products::where('bar_store_id',$barstore->id)->get();

        if(count($product) == 0){
            $barstore->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro");
        }
    }
    public function copy($id){
        $bar = $this->findBar($id);
        if (! $bar instanceof BarStore) {
            return $bar;
        }

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

    private function findBar(string $id)
    {
        $bar = BarStore::find($id);

        if (! $bar) {
            return response()->json(['message' => 'Bar não encontrado.'], 404);
        }

        return $this->denyEventAccess($bar->event_id) ?? $bar;
    }
}
