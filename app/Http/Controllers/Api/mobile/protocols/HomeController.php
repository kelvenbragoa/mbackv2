<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvite;
use App\Models\Event;
use App\Models\SellDetails;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id){


        $event = Event::find($id);
        $all_tickets = SellDetails::where('event_id',$event->id)->count();
        $pending_tickets = SellDetails::where('event_id',$event->id)->where('status',1)->count();
        $done_tickets = SellDetails::where('event_id',$event->id)->where('status',0)->count();

        $all_invites = CustomerInvite::where('event_id',$event->id)->count();
        $pending_invites = CustomerInvite::where('event_id',$event->id)->where('status',1)->count();
        $done_invites = CustomerInvite::where('event_id',$event->id)->where('status',0)->count();



        $array[] = array(
            'all_tickets' => $all_tickets,
            'pending_tickets' => $pending_tickets,
            'done_tickets' => $done_tickets,

            'all_invites' => $all_invites,
            'pending_invites' => $pending_invites,
            'done_invites' => $done_invites,
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
