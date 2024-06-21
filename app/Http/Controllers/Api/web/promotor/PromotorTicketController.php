<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Sell;
use App\Models\Ticket;
use Illuminate\Http\Request;

class PromotorTicketController extends Controller
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

        $ticket = Ticket::create([
            'name'=>$data['name'],
            'price'=>$data['price'],
            'description'=>$data['description'],
            'event_id'=>$data['event_id'],
            'start_date'=>date('Y-m-d',strtotime($data['start_date'])),
            'end_date'=>date('Y-m-d',strtotime($data['end_date'])),
            'start_time'=>$data['start_time'],
            'end_time'=>$data['end_time'],
            'max_qtd'=>$data['max_qtd'],
            'is_package'=>0

        ]);

        return response()->json($ticket);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $ticket = Ticket::find($id);

        return response()->json(["ticket"=>$ticket]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $ticket = Ticket::find($id);
        return response()->json(["ticket"=>$ticket]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $ticket = Ticket::find($id);

        $data['start_date'] = date('Y-m-d',strtotime($data['start_date']));
        $data['end_date'] = date('Y-m-d',strtotime($data['end_date']));

        $ticket->update($data);
        return response()->json([
            "ticket"=>$ticket
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
