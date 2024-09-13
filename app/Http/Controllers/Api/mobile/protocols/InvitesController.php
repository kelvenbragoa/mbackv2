<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvite;
use App\Models\Event;
use Illuminate\Http\Request;

class InvitesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id){

        $event = Event::find($id);

        $all_invites = CustomerInvite::where('event_id',$event->id)->with('event:id,name,start_date')->with('invite:id,name')->get();
        
        

        $array[] = array(
            'all_invites' => $all_invites,
        );

        return response([
            'all_invites' => CustomerInvite::where('event_id',$event->id)->with('event:id,name,start_date')->with('invite:id,name')->get()
        ],200);

    }

    public function pending($id){
        $event = Event::find($id);


        return response([
            'pending_invites' => CustomerInvite::where('event_id',$event->id)->with('event:id,name,start_date')->with('invite:id,name')->where('status',1)->orderBy('id','desc')->get(),
        ],200);

    }

    public function done($id){
        $event = Event::find($id);

        return response([
            'done_invites' => CustomerInvite::where('event_id',$event->id)->with('event:id,name,start_date')->with('invite:id,name')->where('status',0)->orderBy('id','desc')->get(),
        ],200);
    }

    public function invitesdetail($id){
        

        

        return response([
            'invite' => CustomerInvite::where('id',$id)->with('event:id,name,start_date')->with('invite:id,name')->get(),
        ],200);
    }


    public function verifyinvites($id){
        $invite = CustomerInvite::find($id);


        $invite->update([
            'status'=>0
        ]);

        return response([

            'message' => 'Convite Verificado Com sucesso'
        ], 200);
    }

    public function status($id){
        $invite = CustomerInvite::find($id);
        return response([
            'status' => $invite->status
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
