<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\CartBar;
use App\Models\Products;
use App\Models\SellBar;
use App\Models\SellDetailBar;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id){

        $user = Barman::find($id);

        $products = Products::where('event_id',$user->event_id)->where('bar_store_id',$user->bar_store_id)->sum('qtd');
        $carts = CartBar::where('user_id',$user->id)->where('event_id',$user->event_id)->sum('qtd');
        $sells = SellDetailBar::where('user_id',$user->id)->where('event_id',$user->event_id)->sum('qtd');
        $verified = SellBar::where('verified_by',$user->id)->where('event_id',$user->event_id)->count();

        $products_int = $products;
        $carts_int = $carts;
        $sells_int = $sells;
        $verified_int = $verified;

        $array[] = array(
            'products' => intval($products_int),
            'carts' => intval($carts_int),
            'sells' => intval($sells_int),
            'verified'=>intval($verified_int),
        );

        return response([
            'home' => $array,
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
