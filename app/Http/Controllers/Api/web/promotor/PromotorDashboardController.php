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
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\Ticket;
use Carbon\Carbon;
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


    public function bilhetes($id){

        // $event = Event::with('tickets.sells')->with('invites.customers')->with('barstores.sells')->with('products.sells')->with('products.barstore')->find($id);
        // $tickets = Ticket::where('event_id',$id)->where('is_package',0)->orderBy('id','desc')->count();
        // $packages = Ticket::where('event_id',$id)->where('is_package',1)->orderBy('id','desc')->count();
        // $barstores = BarStore::where('event_id',$id)->with('products')->orderBy('id','desc')->count();
        // $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->count();
        // $product = Products::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        // $protocols = Protocol::where('event_id',$id)->orderBy('name','asc')->count();
        // $barmans = Barman::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        // $invites = Invite::where('event_id',$id)->orderBy('name','asc')->count();

        $tickets = Ticket::where('event_id',$id)->where('is_package',0)->orderBy('id','desc')->get();
        $ticket_issued = Sell::where('event_id',$id)->with('ticket')->with('user')->orderBy('id','desc')->get();

        $allsells_value = Sell::where('event_id',$id)->sum('total');
        $allsells_total = Sell::where('event_id',$id)->sum('qty');

        $allsells_value_today = Sell::where('event_id',$id)->whereDate('created_at',now())->sum('total');
        $allsells_total_today = Sell::where('event_id',$id)->whereDate('created_at',now())->sum('qty');

        $allsells_value_week = Sell::where('event_id',$id)->whereBetween('created_at',[Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('total');
        $allsells_total_week = Sell::where('event_id',$id)->whereBetween('created_at',[Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('qty');

        $allsells_value_month = Sell::where('event_id',$id)->whereMonth('created_at',date('m'))->sum('total');
        $allsells_total_month = Sell::where('event_id',$id)->whereMonth('created_at',date('m'))->sum('qty');

        $ticket_report = [];
        $dataTicketDay = [];
        $dataTicketMonth = [];

        foreach ($tickets as $ticket) {

            for ($x = 1; $x <= 31; $x++) {
                $ticketChartDay = Sell::whereDay('created_at',$x)->whereMonth('created_at',date('m'))->whereYear('created_at',date('Y'))->where('ticket_id',$ticket->id)->sum('total');
                $dataTicketDay[]=$ticketChartDay;
            }
    
            for ($x = 1; $x <= 12; $x++) {
                $ticketChartMonth = Sell::whereMonth('created_at',$x)->whereYear('created_at',date('Y'))->where('ticket_id',$ticket->id)->sum('total');
                $dataTicketMonth[]=$ticketChartMonth;
            }


            $ticket_report[] = array(
                'name' => $ticket->name,
                'total' => $ticket->sell->sum('qty'),
                'value' => $ticket->sell->sum('total'),

                'total_today' => Sell::where('ticket_id',$ticket->id)->whereDate('created_at',now())->sum('qty'),
                'value_today' => Sell::where('ticket_id',$ticket->id)->whereDate('created_at',now())->sum('total'),

                'total_week' => Sell::where('ticket_id',$ticket->id)->whereBetween('created_at',[Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('qty'),
                'value_week' => Sell::where('ticket_id',$ticket->id)->whereBetween('created_at',[Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('total'),

                'total_month' => Sell::where('ticket_id',$ticket->id)->whereMonth('created_at',date('m'))->sum('qty'),
                'value_month' => Sell::where('ticket_id',$ticket->id)->whereMonth('created_at',date('m'))->sum('total'),

                'dataTicketDay'=> $dataTicketDay,
                'dataTicketMonth'=>$dataTicketMonth,

                // 'dataTicketDay'=>[
                //     'labels' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31'],
                //     'datasets' => [
                //         [
                //             'label' => 'Vendas Diárias',
                //             "backgroundColor"=> "#3b82f6", 
                //             "borderColor"=> "#3b82f6",
                //             'data' => $dataTicketDay,
                //         ],
                //     ]
                // ]

                // 'dataTicketDay'=> $dataTicketDay,
                // 'dataTicketMonth'=>$dataTicketMonth,
            );
            $dataTicketDay = [];
            $dataTicketMonth = [];
        }




        




        return response()->json([
            'allsells_value' => $allsells_value,
            'allsells_total' => $allsells_total,

            'allsells_value_today' => $allsells_value_today,
            'allsells_total_today' => $allsells_total_today,

            'allsells_value_week' => $allsells_value_week,
            'allsells_total_week' => $allsells_total_week,

            'allsells_value_month' => $allsells_value_month,
            'allsells_total_month' => $allsells_total_month,

            'ticket_report' => $ticket_report,
            'tickets_issued' => $ticket_issued



        ]);

    }
}
