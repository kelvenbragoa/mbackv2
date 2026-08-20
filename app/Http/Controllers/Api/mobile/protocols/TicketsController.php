<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SellDetails;
use Illuminate\Http\Request;

class TicketsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id){

        $event = Event::find($id);

        $all_tickets = SellDetails::where('event_id',$event->id)->with('event:id,name,start_date')->with('ticket:id,name,price')->with('user:id,name,mobile,email')->get();
        
        

        $array[] = array(
            'all_tickets' => $all_tickets,
        );

        return response([
            'all_tickets' => SellDetails::where('event_id',$event->id)->with('event:id,name,start_date')->with('ticket:id,name,price')->with('user:id,name,mobile,email')->with('sell')->with('sell.transaction')->orderBy('id','desc')->get(),
        ],200);

    }


    public function pending($id){
        $event = Event::find($id);

        $pending_tickets = SellDetails::where('event_id',$event->id)->where('status',1)->get();

        $array[] = array(
            'pending_tickets' => $pending_tickets,
        );

        return response([
            'pending_tickets' => SellDetails::where('event_id',$event->id)->with('event:id,name,start_date')->with('ticket:id,name,price')->with('user:id,name,mobile,email')->with('sell')->with('sell.transaction')->where('status',1)->orderBy('id','desc')->get(),
        ],200);

    }

    public function done($id){
        $event = Event::find($id);

        $done_tickets = SellDetails::where('event_id',$event->id)->where('status',0)->get();

        $array[] = array(
            'done_tickets' => $done_tickets,
        );

        return response([
            'done_tickets' => SellDetails::where('event_id',$event->id)->with('event:id,name,start_date')->with('ticket:id,name,price')->with('user:id,name,mobile,email')->with('sell')->with('sell.transaction')->where('status',0)->orderBy('id','desc')->get(),
        ],200);
    }

    public function ticketdetail($id){
        

        

        return response([
            'ticket' => SellDetails::where('id',$id)->with('event:id,name,start_date')->with('ticket:id,name,price')->with('user:id,name,mobile,email')->with('sell')->with('sell.transaction')->orderBy('id','desc')->get(),
        ],200);
    }


    public function verifyticket($id, $userid){
        $ticket = SellDetails::findByAccessCode((string) $id);

        if (! $ticket) {
            return response([
                'message' => 'Bilhete não encontrado.',
            ], 404);
        }

        if ((int) $ticket->status === 0 || $ticket->verified_at !== null) {
            return response([
                'message' => 'Este bilhete já foi verificado.',
            ], 409);
        }

        $ticket->update([
            'status' => 0,
            'verified_by' => $userid,
            'verified_at' => now(),
        ]);

        return response([
            'message' => 'Bilhete Verificado Com sucesso',
        ], 200);
    }

    public function status($id){
        $ticket = SellDetails::findByAccessCode((string) $id);

        if (! $ticket) {
            return response([
                'message' => 'Bilhete não encontrado.',
            ], 404);
        }

        return response([
            'id' => $ticket->id,
            'status' => $ticket->status,
            'event_id' => $ticket->event_id,
            'ticket_number' => $ticket->ticket_number,
        ], 200);
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
