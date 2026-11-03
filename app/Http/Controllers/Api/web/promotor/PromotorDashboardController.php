<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Exports\BarSalesExport;
use App\Exports\TicketSalesExport;
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
use App\Models\SellBar;
use App\Models\SellDetailBar;
use App\Models\SellDetails;
use App\Models\SellDetailShop;
use App\Models\SellShop;
use App\Models\ShopProduct;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;


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

        $event = Event::with([
                'category',
                'tickets' => fn ($query) => $query->withCount('stockSells'),
                'invites' => fn ($query) => $query->withCount('customers'),
                'barstores' => fn ($query) => $query->withCount('products')
                    ->withSum('sells as sold_qtd', 'qtd')
                    ->withSum('sells as sold_value', 'total'),
                'products' => fn ($query) => $query->with('barstore:id,name')
                    ->withSum('sells as sold_qtd', 'qtd')
                    ->withSum('sells as sold_value', 'total'),
                'shopProducts.variants',
            ])
            ->find($id);

        $revenueByTicket = Sell::where('event_id', $id)
            ->selectRaw('ticket_id, SUM(total) as total')
            ->groupBy('ticket_id')
            ->pluck('total', 'ticket_id');

        $event->tickets->each(function (Ticket $ticket) use ($revenueByTicket) {
            $ticket->sold_value = (float) ($revenueByTicket[$ticket->id] ?? 0);
            $ticket->available_quantity = max(0, (int) $ticket->max_qtd - (int) $ticket->stock_sells_count);
        });

        $event->products->concat($event->barstores)->each(function ($row) {
            $row->sold_qtd = (int) $row->sold_qtd;
            $row->sold_value = (float) $row->sold_value;
        });
        $tickets = Ticket::where('event_id',$id)->where('is_package',0)->orderBy('id','desc')->count();
        $packages = Ticket::where('event_id',$id)->where('is_package',1)->orderBy('id','desc')->count();
        $barstores = BarStore::where('event_id',$id)->with('products')->orderBy('id','desc')->count();
        $lineups = LineUp::where('event_id',$id)->orderBy('id','desc')->count();
        $product = Products::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        $protocols = Protocol::where('event_id',$id)->orderBy('name','asc')->count();
        $barmans = Barman::where('event_id',$id)->with('barstore')->orderBy('name','asc')->count();
        $invites = Invite::where('event_id',$id)->orderBy('name','asc')->count();
        $shop = ShopProduct::where('event_id', $id)->count();
        $totalamount = (float) $event->sell_bar()->sum('total');
        $shopRevenue = (float) SellShop::where('event_id', $id)->where('status', SellShop::STATUS_PAID)->sum('total');

        $shopPaidLines = SellDetailShop::where('event_id', $id)
            ->whereHas('sell', function ($query) {
                $query->where('status', SellShop::STATUS_PAID);
            })
            ->get()
            ->groupBy('shop_product_id');

        $event->shopProducts->each(function ($product) use ($shopPaidLines) {
            $lines = $shopPaidLines->get($product->id, collect());
            $product->sold_qtd = (int) $lines->sum('qtd');
            $product->sold_value = (float) $lines->sum('total');
        });

        return response()->json([
            "tickets"=>$tickets,
            "bars"=>$barstores,
            "packages"=>$packages,
            "lineups"=>$lineups,
            "products"=>$product,
            "protocols"=>$protocols,
            "barmans"=>$barmans,
            'invites'=>$invites,
            'shop'=>$shop,
            'event'=>$event,
            'ticket_revenue' => (float) $revenueByTicket->sum(),
            'tickets_sold' => SellDetails::where('event_id', $id)->consumingStock()->count(),
            'invites_issued' => (int) $event->invites->sum('customers_count'),
            'totalamount'=>$totalamount,
            'shop_revenue'=>$shopRevenue,
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
        return $this->ticketSalesDashboard($id, false);
    }

    public function pacotes($id){
        return $this->ticketSalesDashboard($id, true);
    }

    public function vendas(Request $request, $id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $search = trim((string) $request->query('search', ''));
        $sortField = in_array($request->query('sort_field'), ['qty', 'total', 'created_at'], true)
            ? $request->query('sort_field')
            : 'id';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $sells = Sell::where('event_id', $id)
            ->when($request->filled('ticket_id'), fn ($query) => $query->where('ticket_id', (int) $request->query('ticket_id')))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhereHas('ticket', fn ($ticket) => $ticket->where('name', 'like', $like))
                        ->orWhereHas('selldetails', fn ($detail) => $detail->where('form_answers', 'like', $like));
                });
            })
            ->with(['ticket:id,name', 'ticket.formFields', 'selldetails:id,sell_id,form_answers'])
            ->orderBy($sortField, $sortOrder)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($sells);
    }

    private function ticketSalesDashboard($id, bool $isPackage)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $tickets = Ticket::where('event_id', $id)->where('is_package', $isPackage ? 1 : 0)->orderBy('id', 'desc')->get(['id', 'name']);
        $ticketIds = $tickets->pluck('id');

        [$periodTotals, $bindings] = $this->periodSums(['total' => 'qty', 'value' => 'total']);

        $all = Sell::where('event_id', $id)->selectRaw($periodTotals, $bindings)->first();

        $byTicket = Sell::whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, '.$periodTotals, $bindings)
            ->groupBy('ticket_id')
            ->get()
            ->keyBy('ticket_id');

        [$daily, $monthly] = $this->revenueSeries(Sell::whereIn('ticket_id', $ticketIds), 'ticket_id');
        $series = fn ($rows, int $length) => $this->fillSeries($rows, $length);

        $ticket_report = $tickets->map(function (Ticket $ticket) use ($byTicket, $daily, $monthly, $series) {
            $totals = $byTicket->get($ticket->id);

            return [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'total' => (int) ($totals->total ?? 0),
                'value' => (float) ($totals->value ?? 0),
                'total_today' => (int) ($totals->total_today ?? 0),
                'value_today' => (float) ($totals->value_today ?? 0),
                'total_week' => (int) ($totals->total_week ?? 0),
                'value_week' => (float) ($totals->value_week ?? 0),
                'total_month' => (int) ($totals->total_month ?? 0),
                'value_month' => (float) ($totals->value_month ?? 0),
                'dataTicketDay' => $series($daily->get($ticket->id), 31),
                'dataTicketMonth' => $series($monthly->get($ticket->id), 12),
            ];
        })->values();

        return response()->json([
            'allsells_value' => (float) $all->value,
            'allsells_total' => (int) $all->total,
            'allsells_value_today' => (float) $all->value_today,
            'allsells_total_today' => (int) $all->total_today,
            'allsells_value_week' => (float) $all->value_week,
            'allsells_total_week' => (int) $all->total_week,
            'allsells_value_month' => (float) $all->value_month,
            'allsells_total_month' => (int) $all->total_month,
            'ticket_report' => $ticket_report,
        ]);
    }

    public function bar($id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        [$sellSums, $sellBindings] = $this->periodSums(['value' => 'total', 'orders' => '1']);
        [$itemSums, $itemBindings] = $this->periodSums(['items' => 'qtd']);

        $sells = SellBar::where('event_id', $id);
        $details = SellDetailBar::where('event_id', $id);

        $totals = (clone $sells)->selectRaw($sellSums, $sellBindings)->first();
        $items = (clone $details)->selectRaw($itemSums, $itemBindings)->first();

        $byStatus = (clone $sells)->selectRaw('status, COUNT(*) as orders, SUM(total) as value')->groupBy('status')->get()->keyBy('status');

        $byMethod = (clone $sells)
            ->selectRaw('method, COUNT(*) as orders, SUM(total) as value')
            ->groupBy('method')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['method' => $row->method, 'orders' => (int) $row->orders, 'value' => (float) $row->value]);

        $withoutItems = (clone $sells)->doesntHave('selldetails')->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as value')->first();

        $barSums = (clone $sells)->selectRaw('bar_store_id, '.$sellSums, $sellBindings)->groupBy('bar_store_id')->get()->keyBy('bar_store_id');
        $barItems = (clone $details)->selectRaw('bar_store_id, '.$itemSums, $itemBindings)->groupBy('bar_store_id')->get()->keyBy('bar_store_id');
        [$daily, $monthly] = $this->revenueSeries(clone $sells, 'bar_store_id');

        $bars = BarStore::where('event_id', $id)->orderBy('name')->get(['id', 'name'])->map(function (BarStore $bar) use ($barSums, $barItems, $daily, $monthly) {
            $sum = $barSums->get($bar->id);
            $item = $barItems->get($bar->id);

            return [
                'id' => $bar->id,
                'name' => $bar->name,
                'value' => (float) ($sum->value ?? 0),
                'value_today' => (float) ($sum->value_today ?? 0),
                'value_week' => (float) ($sum->value_week ?? 0),
                'value_month' => (float) ($sum->value_month ?? 0),
                'orders' => (int) ($sum->orders ?? 0),
                'items' => (int) ($item->items ?? 0),
                'items_today' => (int) ($item->items_today ?? 0),
                'items_week' => (int) ($item->items_week ?? 0),
                'items_month' => (int) ($item->items_month ?? 0),
                'dataDay' => $this->fillSeries($daily->get($bar->id), 31),
                'dataMonth' => $this->fillSeries($monthly->get($bar->id), 12),
            ];
        })->values();

        $productSales = (clone $details)
            ->selectRaw('product_id, SUM(qtd) as qtd, SUM(total) as value')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $products = Products::where('event_id', $id)->with('barstore:id,name')->get()
            ->map(function (Products $product) use ($productSales) {
                $sale = $productSales->get($product->id);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'bar_store_id' => $product->bar_store_id,
                    'bar' => $product->barstore->name ?? null,
                    'sell_price' => (float) $product->sell_price,
                    'stock' => (int) $product->qtd,
                    'sold_qtd' => (int) ($sale->qtd ?? 0),
                    'sold_value' => (float) ($sale->value ?? 0),
                ];
            })
            ->sortByDesc('sold_value')
            ->values();

        $barmanSales = (clone $sells)
            ->selectRaw('user_id, COUNT(*) as orders, SUM(total) as value')
            ->selectRaw("COALESCE(SUM(CASE WHEN method = 'dinheiro' THEN total END), 0) as cash")
            ->selectRaw("COALESCE(SUM(CASE WHEN method = 'cashless' THEN total END), 0) as cashless")
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) as pending')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $barmen = Barman::where('event_id', $id)->with('barstore:id,name')->orderBy('name')->get(['id', 'name', 'bar_store_id'])
            ->map(function (Barman $barman) use ($barmanSales) {
                $sale = $barmanSales->get($barman->id);
                $value = (float) ($sale->value ?? 0);
                $cash = (float) ($sale->cash ?? 0);
                $cashless = (float) ($sale->cashless ?? 0);

                return [
                    'id' => $barman->id,
                    'name' => $barman->name,
                    'bar_store_id' => $barman->bar_store_id,
                    'bar' => $barman->barstore->name ?? null,
                    'orders' => (int) ($sale->orders ?? 0),
                    'value' => $value,
                    'cash' => $cash,
                    'cashless' => $cashless,
                    'electronic' => $value - $cash - $cashless,
                    'pending' => (int) ($sale->pending ?? 0),
                ];
            })
            ->sortByDesc('value')
            ->values();

        return response()->json([
            'value' => (float) $totals->value,
            'value_today' => (float) $totals->value_today,
            'value_week' => (float) $totals->value_week,
            'value_month' => (float) $totals->value_month,
            'orders' => (int) $totals->orders,
            'orders_today' => (int) $totals->orders_today,
            'orders_week' => (int) $totals->orders_week,
            'orders_month' => (int) $totals->orders_month,
            'items' => (int) $items->items,
            'items_today' => (int) $items->items_today,
            'items_week' => (int) $items->items_week,
            'items_month' => (int) $items->items_month,
            'delivered' => ['orders' => (int) ($byStatus[0]->orders ?? 0), 'value' => (float) ($byStatus[0]->value ?? 0)],
            'pending' => ['orders' => (int) ($byStatus[1]->orders ?? 0), 'value' => (float) ($byStatus[1]->value ?? 0)],
            'without_items' => ['orders' => (int) $withoutItems->orders, 'value' => (float) $withoutItems->value],
            'by_method' => $byMethod,
            'bars' => $bars,
            'products' => $products,
            'barmen' => $barmen,
        ]);
    }

    public function bar_vendas(Request $request, $id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $search = trim((string) $request->query('search', ''));
        $sortField = in_array($request->query('sort_field'), ['total', 'created_at'], true)
            ? $request->query('sort_field')
            : 'id';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $sells = SellBar::where('event_id', $id)
            ->when($request->filled('bar_store_id'), fn ($query) => $query->where('bar_store_id', (int) $request->query('bar_store_id')))
            ->when($request->filled('barman_id'), fn ($query) => $query->where('user_id', (int) $request->query('barman_id')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->query('method')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (int) $request->query('status')))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($query) use ($like, $search) {
                    $query->whereHas('user', fn ($barman) => $barman->where('name', 'like', $like))
                        ->orWhereHas('selldetails.product', fn ($product) => $product->where('name', 'like', $like));
                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            })
            ->with(['user:id,name', 'barstore:id,name', 'selldetails:id,sell_id,product_id,qtd,price,total', 'selldetails.product:id,name'])
            ->orderBy($sortField, $sortOrder)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($sells);
    }

    public function bar_sales_export($event_id)
    {
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

        $event = Event::findOrFail($event_id);

        return Excel::download(new BarSalesExport($event), 'vendas-bar-'.$event->id.'.xlsx');
    }

    /**
     * SUM columns for the whole range plus today / this week / this month, as "alias", "alias_today", ...
     */
    private function periodSums(array $metrics): array
    {
        $now = Carbon::now();
        $periods = [
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ];

        $columns = [];
        $bindings = [];
        foreach ($metrics as $alias => $column) {
            $columns[] = "COALESCE(SUM({$column}), 0) as {$alias}";
            foreach ($periods as $key => [$from, $to]) {
                $columns[] = "COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN {$column} END), 0) as {$alias}_{$key}";
                array_push($bindings, $from, $to);
            }
        }

        return [implode(', ', $columns), $bindings];
    }

    /**
     * Revenue per group for each day of the current month and each month of the current year.
     */
    private function revenueSeries($query, string $groupColumn): array
    {
        $now = Carbon::now();

        $daily = (clone $query)
            ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw("{$groupColumn}, DAY(created_at) as bucket, SUM(total) as value")
            ->groupBy($groupColumn, 'bucket')
            ->get()
            ->groupBy($groupColumn);

        $monthly = (clone $query)
            ->whereBetween('created_at', [$now->copy()->startOfYear(), $now->copy()->endOfYear()])
            ->selectRaw("{$groupColumn}, MONTH(created_at) as bucket, SUM(total) as value")
            ->groupBy($groupColumn, 'bucket')
            ->get()
            ->groupBy($groupColumn);

        return [$daily, $monthly];
    }

    private function fillSeries($rows, int $length): array
    {
        $values = array_fill(1, $length, 0.0);
        foreach ($rows ?? [] as $row) {
            $values[(int) $row->bucket] = (float) $row->value;
        }

        return array_values($values);
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

    public function loja($id)
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $paidSells = SellShop::where('event_id', $id)
            ->where('status', SellShop::STATUS_PAID)
            ->get();

        $details = $paidSells->isEmpty()
            ? collect()
            : SellDetailShop::whereIn('sell_id', $paidSells->pluck('id'))->get();

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $now = Carbon::now();

        $sellsInPeriod = function ($predicate) use ($paidSells) {
            return $paidSells->filter($predicate);
        };

        $qtyForSells = function ($sells) use ($details) {
            return (int) $details->whereIn('sell_id', $sells->pluck('id'))->sum('qtd');
        };

        $todaySells = $sellsInPeriod(fn ($sell) => $sell->created_at && $sell->created_at->isToday());
        $weekSells = $sellsInPeriod(fn ($sell) => $sell->created_at && $sell->created_at->between($weekStart, $weekEnd));
        $monthSells = $sellsInPeriod(fn ($sell) => $sell->created_at
            && $sell->created_at->month === (int) $now->month
            && $sell->created_at->year === (int) $now->year);

        $ticket_report = [];
        foreach ($details->groupBy(function ($line) {
            return $line->product_name ?: 'Produto';
        }) as $name => $group) {
            $dataTicketDay = [];
            for ($x = 1; $x <= 31; $x++) {
                $dataTicketDay[] = (float) $group->filter(function ($line) use ($x, $now) {
                    return $line->created_at
                        && (int) $line->created_at->day === $x
                        && (int) $line->created_at->month === (int) $now->month
                        && (int) $line->created_at->year === (int) $now->year;
                })->sum('total');
            }

            $dataTicketMonth = [];
            for ($x = 1; $x <= 12; $x++) {
                $dataTicketMonth[] = (float) $group->filter(function ($line) use ($x, $now) {
                    return $line->created_at
                        && (int) $line->created_at->month === $x
                        && (int) $line->created_at->year === (int) $now->year;
                })->sum('total');
            }

            $ticket_report[] = [
                'name' => $name,
                'total' => (int) $group->sum('qtd'),
                'value' => (float) $group->sum('total'),
                'total_today' => (int) $group->filter(fn ($line) => $line->created_at && $line->created_at->isToday())->sum('qtd'),
                'value_today' => (float) $group->filter(fn ($line) => $line->created_at && $line->created_at->isToday())->sum('total'),
                'total_week' => (int) $group->filter(fn ($line) => $line->created_at && $line->created_at->between($weekStart, $weekEnd))->sum('qtd'),
                'value_week' => (float) $group->filter(fn ($line) => $line->created_at && $line->created_at->between($weekStart, $weekEnd))->sum('total'),
                'total_month' => (int) $group->filter(function ($line) use ($now) {
                    return $line->created_at
                        && (int) $line->created_at->month === (int) $now->month
                        && (int) $line->created_at->year === (int) $now->year;
                })->sum('qtd'),
                'value_month' => (float) $group->filter(function ($line) use ($now) {
                    return $line->created_at
                        && (int) $line->created_at->month === (int) $now->month
                        && (int) $line->created_at->year === (int) $now->year;
                })->sum('total'),
                'dataTicketDay' => $dataTicketDay,
                'dataTicketMonth' => $dataTicketMonth,
            ];
        }

        $orders = SellShop::where('event_id', $id)
            ->whereIn('status', [SellShop::STATUS_PAID, SellShop::STATUS_CANCELLED])
            ->with(['details', 'transaction'])
            ->orderByDesc('id')
            ->get()
            ->map(function (SellShop $sell) {
                $payload = $sell->toOrderArray();
                $payload['qty'] = (int) $sell->details->sum('qtd');
                $payload['product_names'] = $sell->details->pluck('product_name')->filter()->implode(', ');
                $payload['can_cancel'] = $sell->isPaid() && ! $sell->isPickedUp();
                $payload['pickup_label'] = (int) $sell->status === SellShop::STATUS_CANCELLED
                    ? 'Cancelada'
                    : ($sell->isPickedUp() ? 'Levantado' : 'Pendente');

                return $payload;
            })
            ->values();

        return response()->json([
            'allsells_value' => (float) $paidSells->sum('total'),
            'allsells_total' => $qtyForSells($paidSells),
            'allsells_value_today' => (float) $todaySells->sum('total'),
            'allsells_total_today' => $qtyForSells($todaySells),
            'allsells_value_week' => (float) $weekSells->sum('total'),
            'allsells_total_week' => $qtyForSells($weekSells),
            'allsells_value_month' => (float) $monthSells->sum('total'),
            'allsells_total_month' => $qtyForSells($monthSells),
            'pending_pickup' => $paidSells->whereNull('picked_up_at')->count(),
            'picked_up' => $paidSells->filter(fn ($sell) => $sell->picked_up_at !== null)->count(),
            'cancelled_count' => SellShop::where('event_id', $id)->where('status', SellShop::STATUS_CANCELLED)->count(),
            'ticket_report' => $ticket_report,
            'orders_issued' => $orders,
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


    public function shop_report($event_id)
    {
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

        $event = Event::with('user')->find($event_id);
        $products = ShopProduct::where('event_id', $event_id)->with('variants')->orderBy('name')->get();
        $paid = SellShop::where('event_id', $event_id)->where('status', SellShop::STATUS_PAID);
        $paidIds = (clone $paid)->pluck('id');
        $details = $paidIds->isEmpty()
            ? collect()
            : SellDetailShop::whereIn('sell_id', $paidIds)->get();
        $soldByProduct = $details->groupBy('shop_product_id');

        $totals = [
            'products' => $products->count(),
            'orders' => (clone $paid)->count(),
            'qty' => (int) $details->sum('qtd'),
            'revenue' => (float) (clone $paid)->sum('total'),
            'picked_up' => (clone $paid)->whereNotNull('picked_up_at')->count(),
            'pending' => (clone $paid)->whereNull('picked_up_at')->count(),
        ];

        $productRows = $products->map(function (ShopProduct $product) use ($soldByProduct) {
            $lines = $soldByProduct->get($product->id, collect());

            return [
                'name' => $product->name,
                'stock' => $product->stock(),
                'sold_qtd' => (int) $lines->sum('qtd'),
                'sold_value' => (float) $lines->sum('total'),
            ];
        });

        $orders = (clone $paid)->with('details')->orderByDesc('id')->get();

        $pdf = Pdf::loadView('pdf.shopreport', compact('event', 'totals', 'productRows', 'orders'))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true',
        ]);

        return $pdf->setPaper('a4')->download('shopreport'.$event_id.'.pdf');
    }

    public function ticket_report($event_id){
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

        $event = Event::with([
            'user',
            'city',
            'province',
            'tickets' => fn ($query) => $query->withCount('stockSells'),
            'packages' => fn ($query) => $query->withCount('stockSells'),
        ])->find($event_id);

        $online = function ($query) {
            $query->where('user_id', '!=', 0)
                ->orWhereNull('user_id');
        };

        $tickets_local = Sell::where('event_id', $event->id)->where('user_id', 0)->with('transaction')->get();
        $tickets_online = Sell::where('event_id', $event->id)->where($online)->get();

        $tickets_lote = $tickets_local->filter(fn (Sell $sell) => $sell->saleChannel() === 'lote');
        $tickets_box_office = $tickets_local->reject(fn (Sell $sell) => $sell->saleChannel() === 'lote');

        $lote_status = $tickets_lote->isEmpty()
            ? collect()
            : SellDetails::whereIn('sell_id', $tickets_lote->pluck('id'))
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

        $lote_summary = [
            'used' => (int) ($lote_status[SellDetails::STATUS_USED] ?? 0),
            'unused' => (int) ($lote_status[SellDetails::STATUS_VALID] ?? 0),
            'returned' => (int) ($lote_status[SellDetails::STATUS_RETURNED] ?? 0),
        ];
        $lote_summary['issued'] = array_sum($lote_summary);

        $tickets_local_validated = SellDetails::where('event_id', $event->id)->where('user_id', 0)->where('status', SellDetails::STATUS_USED)->count();
        $tickets_local_pending = SellDetails::where('event_id', $event->id)->where('user_id', 0)->where('status', SellDetails::STATUS_VALID)->count();
        $tickets_online_validated = SellDetails::where('event_id', $event->id)->where($online)->where('status', SellDetails::STATUS_USED)->count();
        $tickets_online_pending = SellDetails::where('event_id', $event->id)->where($online)->where('status', SellDetails::STATUS_VALID)->count();
        $tickets_returned = SellDetails::where('event_id', $event->id)->where('status', SellDetails::STATUS_RETURNED)->count();

        $revenue_by_ticket = Sell::where('event_id', $event->id)
            ->selectRaw('ticket_id, SUM(total) as total')
            ->groupBy('ticket_id')
            ->pluck('total', 'ticket_id');

        $pdf = Pdf::loadView(
            'pdf.ticketreport',
            compact(
                'event',
                'tickets_local',
                'tickets_online',
                'tickets_lote',
                'tickets_box_office',
                'lote_summary',
                'tickets_local_validated',
                'tickets_local_pending',
                'tickets_online_validated',
                'tickets_online_pending',
                'tickets_returned',
                'revenue_by_ticket'
                ))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ])->setPaper('a4');

        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 93,
            $canvas->get_height() - 39,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans'),
            6.5,
            [0.525, 0.573, 0.631]
        );

        return $pdf->stream('relatorio-bilhetes-'.$event->id.'.pdf');


    }

    public function ticket_sales_export($event_id)
    {
        if ($denied = $this->denyEventAccess($event_id)) {
            return $denied;
        }

        $event = Event::findOrFail($event_id);

        return Excel::download(new TicketSalesExport($event), 'vendas-bilhetes-'.$event->id.'.xlsx');
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
