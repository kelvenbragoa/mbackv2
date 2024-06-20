<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\BarStore;
use Illuminate\Http\Request;

class PromotorBarmanController extends Controller
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

        $test = Barman::orderBy('id','desc')->first();
        $string = substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(3/strlen($x)) )),1,6);

        if($test == null){
            $user = $string.'1';
            Barman::create([
                'name'=>$data['name'],
                'mobile'=>$data['mobile'],
                'user'=>$user,
                'bi'=>$data['bi'],
                'password'=>$data['bi'],
                'event_id'=>$data['event_id'],
                'bar_store_id'=>$data['bar_store_id'],

            ]);
        }else{
            $user = $string.$test->id+1;
            Barman::create([
                'name'=>$data['name'],
                'mobile'=>$data['mobile'],
                'user'=>$user,
                'bi'=>$data['bi'],
                'password'=>$data['bi'],
                'event_id'=>$data['event_id'],
                'bar_store_id'=>$data['bar_store_id'],
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
        $barman = Barman::with('barstore')->find($id);
        return response()->json(["barman"=>$barman]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $barman = Barman::with('barstore')->find($id);
        $barstore = BarStore::where("event_id",$barman->event_id)->get();
        return response()->json(["barman"=>$barman,"barstore"=>$barstore]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $barman = Barman::find($id);
        $barman->update($data);
        return response()->json($barman);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
