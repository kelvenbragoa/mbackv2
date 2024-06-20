<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\BarStore;
use App\Models\Event;
use App\Models\Invite;
use App\Models\LineUp;
use App\Models\Products;
use App\Models\Protocol;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotorDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        if(Auth::user()->role_id == 1){
            $events = Event::count();
            $eventspending = Event::where('status_id',3)->count();
            $eventscanceled = Event::where('status_id',1)->count();
            $eventsapproved = Event::where('status_id',2)->count();
            $eventsreview = Event::where('status_id',4)->count();
        }else{
            $events = Event::where('user_id',Auth::user()->id)->count();
            $eventspending = Event::where('user_id',Auth::user()->id)->where('status_id',3)->count();
            $eventscanceled = Event::where('user_id',Auth::user()->id)->where('status_id',1)->count();
            $eventsapproved = Event::where('user_id',Auth::user()->id)->where('status_id',2)->count();
            $eventsreview = Event::where('user_id',Auth::user()->id)->where('status_id',4)->count();
        }
        

        return response()->json([
            "events" => $events,
            "eventsapproved"=>$eventsapproved,
            "eventspending"=>$eventspending,
            "eventscanceled"=>$eventscanceled,
            "eventsreview"=>$eventsreview,

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
        $event = Event::with('tickets.sells')->with('invites.customers')->with('barstores.sells')->with('products.sells')->with('products.barstore')->find($id);
        $tickets = Ticket::where('event_id',$id)->where('is_package',0)->orderBy('id','desc')->count();
        $packages = Ticket::where('event_id',$id)->where('is_package',1)->orderBy('id','desc')->count();
        $barstores = BarStore::where('event_id',$id)->with('products')->orderBy('id','desc')->count();
        $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->count();
        $product = Products::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        $protocols = Protocol::where('event_id',$id)->orderBy('name','asc')->count();
        $barmans = Barman::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        $invites = Invite::where('event_id',$id)->orderBy('name','asc')->count();

        return response()->json([
            "tickets"=>$tickets,
            "bars"=>$barstores,
            "packages"=>$packages,
            "lineups"=>$lineups,
            "products"=>$product,
            "protocols"=>$protocols,
            "barmans"=>$barmans,
            'invites'=>$invites,
            'event'=>$event,
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
