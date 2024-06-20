<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvite;
use App\Models\Invite;
use Illuminate\Http\Request;

class PromotorInviteController extends Controller
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

        $invite = Invite::create([
            'name'=>$data['name'],
            'description'=>$data['description'],
            'event_id'=>$data['event_id'],
        ]);

        return response()->json($invite);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $invite = Invite::find($id);
        $customer = CustomerInvite::where('invite_id', $invite->id)->get();

        return response()->json([
            "invite"=>$invite,
            "customer"=>$customer
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $invite = Invite::find($id);
        return response()->json(["invite"=>$invite]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $invite = Invite::find($id);

        $invite->update($data);
        return response()->json([
            "invite"=>$invite
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $invite = Invite::findOrFail($id);

        $sell = CustomerInvite::where('invite_id',$invite->id)->get();

        if(count($sell) == 0){
            $invite->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro Ao apagar");
        }
    }
}
