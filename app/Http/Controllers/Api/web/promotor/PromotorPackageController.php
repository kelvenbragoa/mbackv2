<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Sell;
use App\Models\Ticket;
use Illuminate\Http\Request;

class PromotorPackageController extends Controller
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

        Ticket::create([
            'name'=>$data['name'],
            'price'=>$data['price'],
            'description'=>$data['description'],
            'event_id'=>$data['event_id'],
            'start_date'=>'2022-01-01',
            'end_date'=>'2022-01-01',
            'start_time'=>'06:00:00',
            'end_time'=>'06:00:00',
            'max_qtd'=>0,
            'is_package'=>1,

        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $ticket = Ticket::find($id);
        return response()->json(["package"=>$ticket]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $ticket = Ticket::find($id);
        return response()->json(["package"=>$ticket]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $ticket = Ticket::find($id);

        $ticket->update($data);
        return response()->json([
            "package"=>$ticket
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $ticket = Ticket::findOrFail($id);

        $sell = Sell::where('ticket_id',$ticket->id)->get();

        if(count($sell) == 0){
            $ticket->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro");
        }
    }
}
