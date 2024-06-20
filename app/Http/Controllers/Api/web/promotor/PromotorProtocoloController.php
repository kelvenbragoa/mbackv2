<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Protocol;
use Illuminate\Http\Request;

class PromotorProtocoloController extends Controller
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

        $test = Protocol::orderBy('id','desc')->first();
        $string = substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(3/strlen($x)) )),1,6);

        if($test == null){
            $user = $string.'1';
            Protocol::create([
                'name'=>$data['name'],
                'mobile'=>$data['mobile'],
                'user'=>$user,
                'bi'=>$data['bi'],
                'password'=>$data['bi'],
                'event_id'=>$data['event_id'],

            ]);
        }else{
            $user = $string.$test->id+1;
            Protocol::create([
                'name'=>$data['name'],
                'mobile'=>$data['mobile'],
                'user'=>$user,
                'bi'=>$data['bi'],
                'password'=>$data['bi'],
                'event_id'=>$data['event_id'],

            ]);
        }
        return response()->json($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $protocolo = Protocol::find($id);
        return response()->json(["protocolo"=>$protocolo]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $protocolo = Protocol::find($id);
        return response()->json(["protocolo"=>$protocolo]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $protocolo = Protocol::find($id);
        $protocolo->update($data);
        return response()->json($protocolo);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
