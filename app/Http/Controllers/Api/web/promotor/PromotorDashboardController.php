<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\BarStore;
use App\Models\CustomerInvite;
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
use Barryvdh\DomPDF\Facade\Pdf;


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
        $totalamount = $event->sell_bar_detail->sum('total');

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
            'totalamount'=>$totalamount
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

    public function pacotes($id){

        $tickets = Ticket::where('event_id',$id)->where('is_package',1)->orderBy('id','desc')->get();
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

    public function convites($id){

        $invites = Invite::where('event_id',$id)->orderBy('id','desc')->get();
        $invites_issued = CustomerInvite::where('event_id',$id)->with('invite')->orderBy('id','desc')->get();

       

        return response()->json([
            'allinvites_total' => $invites->count(),
            'invites_issued' => $invites_issued
        ]);

    }

    public function lineups($id){

        $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->get();
        return response()->json([
            'lineups' => $lineups,
        ]);

    }





    public function reportproducts(string $id){

    }


    public function bar_report($event_id){

        $event = Event::find($event_id);
        $investment = 0;
        $profit = 0;

        foreach($event->products as $item){
            $investment = $investment + ($item->qtd*$item->buy_price);
            $profit = $profit + $item->qtd*$item->sell_price;
        }

         
       
        $pdf = Pdf::loadView('pdf.barreport', compact('event','investment','profit'))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);
        return $pdf->setPaper('a4')->download('invoice.pdf');


    }


    public function ticket_report($event_id){

        $event = Event::find($event_id);
        $sells = SellDetails::where('event_id', $event)->get();
        $tickets_local = Sell::where('event_id',$event->id)->where('user_id',0)->get();
        // $tickets_online = Sell::where('event_id',$event->id)->where('user_id','!=',0)->orWhere('user_id',null)->get();

        $tickets_online = Sell::where('event_id', $event->id)
        ->where(function($query) {
            $query->where('user_id', '!=', 0)
                ->orWhereNull('user_id');
        })
        ->get();


        // return $tickets_online;



        $tickets_local_true = SellDetails::where('event_id',$event->id)->where('user_id',0)->where('status',1)->get();
        $tickets_local_false = SellDetails::where('event_id',$event->id)->where('user_id',0)->where('status',0)->get();
        $tickets_online_true = SellDetails::where('event_id', $event->id)->where('status',1)
        ->where(function($query) {
            $query->where('user_id', '!=', 0)
                ->orWhereNull('user_id');
        });

        $tickets_online_false = SellDetails::where('event_id', $event->id)->where('status',0)
        ->where(function($query) {
            $query->where('user_id', '!=', 0)
                ->orWhereNull('user_id');
        })
        ->get();
        $invites_online_true = SellDetails::where('event_id',$event->id)->where('user_id',55)->where('status',1)->get();
        $invites_online_false = SellDetails::where('event_id',$event->id)->where('user_id',55)->where('status',0)->get();


        $pending_tickets = SellDetails::where('event_id',$event->id)->where('status',1)->count();


        $tickets_local_amount = 0;

        
        foreach($tickets_local as $item){
            $tickets_local_amount =$tickets_local_amount + $item->qty*$item->price;
        }

      

      
       
        $pdf = Pdf::loadView(
            'pdf.ticketreport', 
            compact(
                'event',
                'tickets_local',
                'tickets_online',
                'tickets_local_amount',
                'tickets_local_true',
                'tickets_local_false',
                'tickets_online_true',
                'tickets_online_false',
                'invites_online_true',
                'invites_online_false',
                'pending_tickets',
                'sells'
                ))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);
        return $pdf->setPaper('a4')->stream('invoice.pdf');


    }

    public function bar_store_report($id){

        $barstore = BarStore::find($id);
        $event = Event::find($barstore->event_id);
        $barmans = Barman::where('bar_store_id',$id)->get();
        
        $pdf = Pdf::loadView(
            'superadmin.events.bar-store-report', 
            compact(
                'event',
                'barstore',
                'barmans'
                ))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);
        return $pdf->setPaper('a4')->stream('barstore.pdf');
    }

}
