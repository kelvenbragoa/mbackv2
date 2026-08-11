<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
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
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;


class PromotorDashboardController extends Controller
{
    use AuthorizesEventAccess;

    private const ALLOWED_RANGES = [7, 30, 90, 365];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $days = (int) $request->query('range', 30);
        if (!in_array($days, self::ALLOWED_RANGES, true)) {
            $days = 30;
        }

        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $previousEnd = (clone $start)->subSecond();
        $previousStart = (clone $start)->subDays($days);
        $eventIds = $this->eventIds();

        return response()->json([
            'range' => $days,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'kpis' => $this->kpis($eventIds, $start, $end, $previousStart, $previousEnd),
            'totals' => $this->totals($eventIds),
            'chart' => $this->chart($eventIds, $start, $end),
            'events_by_status' => $this->eventsByStatus($eventIds),
            'top_events' => $this->topEvents($eventIds, $start, $end),
            'recent_sells' => $this->recentSells($eventIds),
            'upcoming_events' => $this->upcomingEvents($eventIds),
            // Compatibilidade com a resposta antiga
            'events' => count($eventIds),
            'eventsapproved' => Event::whereIn('id', $eventIds)->where('status_id', 2)->count(),
            'eventspending' => Event::whereIn('id', $eventIds)->where('status_id', 3)->count(),
            'eventscanceled' => Event::whereIn('id', $eventIds)->where('status_id', 1)->count(),
            'eventsreview' => Event::whereIn('id', $eventIds)->where('status_id', 4)->count(),
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
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

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
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

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
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

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
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $invites = Invite::where('event_id',$id)->orderBy('id','desc')->get();
        $invites_issued = CustomerInvite::where('event_id',$id)->with('invite')->orderBy('id','desc')->get();

       

        return response()->json([
            'allinvites_total' => $invites->count(),
            'invites_issued' => $invites_issued
        ]);

    }

    public function lineups($id){
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->get();
        return response()->json([
            'lineups' => $lineups,
        ]);

    }





    public function reportproducts(string $id){

    }


    public function bar_report($event_id){
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

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
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

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

        if (!$barstore) {
            return response()->json(['message' => 'Bar não encontrado.'], 404);
        }

        if ($denied = $this->denyEventAccess($barstore->event_id)) {
            return $denied;
        }

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

    private function eventIds(): array
    {
        if (Auth::user()->role_id == 1) {
            return Event::orderByDesc('id')->pluck('id')->all();
        }

        return Event::where('user_id', Auth::user()->id)->orderByDesc('id')->pluck('id')->all();
    }

    private function kpis(array $eventIds, Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd): array
    {
        $revenue = $this->paidSells($eventIds, $start, $end)->sum('total');
        $previousRevenue = $this->paidSells($eventIds, $previousStart, $previousEnd)->sum('total');

        $tickets = $this->ticketDetails($eventIds)->whereBetween('created_at', [$start, $end])->count();
        $previousTickets = $this->ticketDetails($eventIds)->whereBetween('created_at', [$previousStart, $previousEnd])->count();

        $orders = $this->paidSells($eventIds, $start, $end)->count();
        $previousOrders = $this->paidSells($eventIds, $previousStart, $previousEnd)->count();

        $activeEvents = Event::whereIn('id', $eventIds)
            ->where('status_id', 2)
            ->whereDate('end_date', '>=', Carbon::today())
            ->count();
        $previousActiveEvents = Event::whereIn('id', $eventIds)
            ->where('status_id', 2)
            ->whereDate('end_date', '>=', $previousStart->toDateString())
            ->whereDate('start_date', '<=', $previousEnd->toDateString())
            ->count();

        return [
            'revenue' => $this->metric($revenue, $previousRevenue),
            'tickets' => $this->metric($tickets, $previousTickets),
            'orders' => $this->metric($orders, $previousOrders),
            'active_events' => $this->metric($activeEvents, $previousActiveEvents),
        ];
    }

    private function totals(array $eventIds): array
    {
        return [
            'events' => count($eventIds),
            'events_approved' => Event::whereIn('id', $eventIds)->where('status_id', 2)->count(),
            'events_pending' => Event::whereIn('id', $eventIds)->where('status_id', 3)->count(),
            'events_canceled' => Event::whereIn('id', $eventIds)->where('status_id', 1)->count(),
            'events_review' => Event::whereIn('id', $eventIds)->where('status_id', 4)->count(),
            'revenue' => (float) Sell::where('status', 1)->whereIn('event_id', $eventIds)->sum('total'),
            'tickets' => SellDetails::whereIn('event_id', $eventIds)->count(),
            'upcoming' => Event::whereIn('id', $eventIds)
                ->where('status_id', 2)
                ->whereDate('start_date', '>=', Carbon::today())
                ->count(),
        ];
    }

    private function chart(array $eventIds, Carbon $start, Carbon $end): array
    {
        $revenueByDay = $this->paidSells($eventIds, $start, $end)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $ticketsByDay = $this->ticketDetails($eventIds)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $revenue = [];
        $tickets = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');
            $revenue[] = round((float) ($revenueByDay[$key] ?? 0), 2);
            $tickets[] = (int) ($ticketsByDay[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'tickets' => $tickets,
        ];
    }

    private function eventsByStatus(array $eventIds): array
    {
        $labels = [
            2 => 'Aprovados',
            3 => 'Pendentes',
            4 => 'Em revisão',
            1 => 'Cancelados',
        ];

        $counts = Event::whereIn('id', $eventIds)
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $result = [];
        foreach ($labels as $statusId => $label) {
            $result[] = [
                'status_id' => $statusId,
                'label' => $label,
                'total' => (int) ($counts[$statusId] ?? 0),
            ];
        }

        return $result;
    }

    private function topEvents(array $eventIds, Carbon $start, Carbon $end): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $rows = $this->paidSells($eventIds, $start, $end)
            ->select('event_id', DB::raw('SUM(total) as revenue'), DB::raw('SUM(qty) as tickets'))
            ->groupBy('event_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $events = Event::whereIn('id', $rows->pluck('event_id'))
            ->get(['id', 'name', 'image', 'status_id', 'start_date'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($events) {
            $event = $events->get($row->event_id);

            return [
                'event_id' => (int) $row->event_id,
                'name' => $event->name ?? 'Evento removido',
                'image' => $event->image ?? null,
                'status_id' => $event->status_id ?? null,
                'start_date' => $event->start_date ?? null,
                'revenue' => round((float) $row->revenue, 2),
                'tickets' => (int) $row->tickets,
            ];
        })->all();
    }

    private function recentSells(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $sells = Sell::where('status', 1)
            ->whereIn('event_id', $eventIds)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'event_id', 'name', 'email', 'mobile', 'qty', 'total', 'created_at']);

        $events = Event::whereIn('id', $sells->pluck('event_id'))->pluck('name', 'id');

        return $sells->map(function ($sell) use ($events) {
            return [
                'id' => $sell->id,
                'name' => $sell->name,
                'email' => $sell->email,
                'mobile' => $sell->mobile,
                'qty' => (int) $sell->qty,
                'total' => (float) $sell->total,
                'created_at' => $sell->created_at,
                'event_id' => $sell->event_id,
                'event_name' => $events[$sell->event_id] ?? 'Evento removido',
            ];
        })->all();
    }

    private function upcomingEvents(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        return Event::whereIn('id', $eventIds)
            ->where('status_id', 2)
            ->whereDate('start_date', '>=', Carbon::today())
            ->orderBy('start_date')
            ->limit(5)
            ->get(['id', 'name', 'image', 'status_id', 'start_date', 'end_date', 'province_id', 'city_id'])
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'image' => $event->image,
                    'status_id' => $event->status_id,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                ];
            })
            ->all();
    }

    private function paidSells(array $eventIds, Carbon $start, Carbon $end)
    {
        return Sell::where('status', 1)
            ->whereIn('event_id', $eventIds ?: [0])
            ->whereBetween('created_at', [$start, $end]);
    }

    private function ticketDetails(array $eventIds)
    {
        return SellDetails::whereIn('event_id', $eventIds ?: [0]);
    }

    private function metric($current, $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous > 0) {
            $change = round((($current - $previous) / $previous) * 100, 1);
        } else {
            $change = $current > 0 ? null : 0.0;
        }

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $change,
        ];
    }

}
