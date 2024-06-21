<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\TemporarySell;
use App\Models\TemporaryTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AdminTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $searchQuery = request('query');
        $transaction = TemporaryTransaction::query()
            ->when(request('query'),function($query,$searchQuery){
                $query->where('reference','like',"%{$searchQuery}%");
            })
            ->with('sell.selldetails')
            ->with('sell.event')
            ->orderBy('id','desc')
            ->paginate(50);


        return response()->json([
            "transaction" => $transaction
        ]);
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
        $temporaryTransaction = TemporaryTransaction::find($id);
        $temporarySell = TemporarySell::find($id);

        $sell = Sell::create([
            'event_id'=>$temporarySell->event_id,
            'ticket_id'=>$temporarySell->ticket_id,
            'qty'=>$temporarySell->qty,
            'price'=>$temporarySell->price,
            'total'=>$temporarySell->price*$temporarySell->qty,
            'status'=>1,
            'name'=>$temporarySell->name,
            'email'=>$temporarySell->email,
            'mobile'=>$temporarySell->mobile,
            'user_id'=>$temporarySell->user_id ?? null,

        ]);

        Transaction::create([
            'sell_id'=>$sell->id,
            'reference'=>$temporaryTransaction->reference,
            'method'=>'mpesa',
            'user_id'=>$temporarySell->user_id ?? null,
        ]);
        
        for ($i=0; $i < $temporarySell->qty; $i++) { 
            SellDetails::create([
                'sell_id'=>$sell->id,
                'event_id'=>$sell->event_id,
                'ticket_id'=>$sell->ticket_id,
                'status'=>1,
                'name'=>$sell->name,
                'email'=>$sell->email,
                'mobile'=>$sell->mobile,
                'user_id'=>$sell->user_id ?? null,
            ]);
        }

        $temporarySell->transaction()->delete();
        $temporarySell->selldetails()->delete();
        $temporarySell->delete();
        
        $transaction = TemporaryTransaction::query()
            ->when(request('query'),function($query,$searchQuery){
                $query->where('reference','like',"%{$searchQuery}%");
            })
            ->with('sell.selldetails')
            ->with('sell.event')
            ->orderBy('id','desc')
            ->paginate(50);


        return response()->json([
            "transaction" => $transaction
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
