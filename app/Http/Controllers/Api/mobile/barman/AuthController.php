<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\BarStore;
use App\Models\Event;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function login(Request $request){
        $data = $request->all();


        $barman = Barman::where('user',$data['user'])->where('password',$data['password'])->first();
        if($barman == null){
            return response(
                [ 'message' => 'Usuario/Password Incorretos'], 403
             );
        }
        $event = Event::find($barman->event_id);
        $bar_store_name = BarStore::find($barman->bar_store_id);

        $initial_date = date('l, d M Y',strtotime($event->start_date)) ;



        $array = array(
            'id' => $barman->id,
            'name' => $barman->name,
            'mobile' => $barman->mobile,
            'bi' => $barman->bi,
            'password' => $barman->password,
            'user' => $barman->user,
            'event_id' => $barman->event_id,
            'event_name'=> $barman->event->name,
            'date'=> $initial_date ,
            'bar_store_id'=>$barman->bar_store_id,
            'bar_store_name'=>$bar_store_name->name,
           
        );
       
        
        return response([
            'user' => $array,
        ],200);
   
    }

    public function user($id){
       
        $barman = Barman::where('id',$id)->first();

        if($barman == null){
            return response(
                [ 'message' => 'Usuario/Password Incorretos'], 403
             );
        }

        $event = Event::find($barman->event_id);

        $initial_date = date('l, d M Y',strtotime($event->start_date)) ;



        $array = array(
            'id' => $barman->id,
            'name' => $barman->name,
            'mobile' => $barman->mobile,
            'bi' => $barman->bi,
            'password' => $barman->password,
            'user' => $barman->user,
            'event_id' => $barman->event_id,
            'event_name'=> $barman->event->name,
            'date'=> $initial_date ,
           
        );
       
        
        return response([
            'user' => $array,
        ],200);


    }

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
