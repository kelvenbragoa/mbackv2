<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\TemporaryTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    private const ALLOWED_RANGES = [7, 30, 90, 365];

    public function index(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a este painel.'], 403);
        }

        $days = (int) $request->query('range', 30);
        if (!in_array($days, self::ALLOWED_RANGES, true)) {
            $days = 30;
        }

        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $previousEnd = (clone $start)->subSecond();
        $previousStart = (clone $start)->subDays($days);

        return response()->json([
            'range' => $days,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'kpis' => $this->kpis($start, $end, $previousStart, $previousEnd),
            'totals' => $this->totals(),
            'chart' => $this->chart($start, $end),
            'events_by_status' => $this->eventsByStatus(),
            'top_events' => $this->topEvents($start, $end),
            'recent_sells' => $this->recentSells(),
            'pending_events' => $this->pendingEvents(),
        ]);
    }

    private function kpis(Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd): array
    {
        $revenue = $this->paidSells($start, $end)->sum('total');
        $previousRevenue = $this->paidSells($previousStart, $previousEnd)->sum('total');

        $tickets = SellDetails::whereBetween('created_at', [$start, $end])->count();
        $previousTickets = SellDetails::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        $orders = $this->paidSells($start, $end)->count();
        $previousOrders = $this->paidSells($previousStart, $previousEnd)->count();

        $users = User::whereBetween('created_at', [$start, $end])->count();
        $previousUsers = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        return [
            'revenue' => $this->metric($revenue, $previousRevenue),
            'tickets' => $this->metric($tickets, $previousTickets),
            'orders' => $this->metric($orders, $previousOrders),
            'users' => $this->metric($users, $previousUsers),
        ];
    }

    private function totals(): array
    {
        return [
            'events' => Event::count(),
            'events_approved' => Event::where('status_id', 2)->count(),
            'events_pending' => Event::where('status_id', 3)->count(),
            'events_canceled' => Event::where('status_id', 1)->count(),
            'events_review' => Event::where('status_id', 4)->count(),
            'users' => User::count(),
            'promotors' => User::where('is_promotor', 1)->count(),
            'revenue' => (float) Sell::where('status', 1)->sum('total'),
            'tickets' => SellDetails::count(),
            'pending_payments' => TemporaryTransaction::count(),
        ];
    }

    private function chart(Carbon $start, Carbon $end): array
    {
        $revenueByDay = $this->paidSells($start, $end)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $ticketsByDay = SellDetails::whereBetween('created_at', [$start, $end])
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

    private function eventsByStatus(): array
    {
        $labels = [
            2 => 'Aprovados',
            3 => 'Pendentes',
            4 => 'Em revisão',
            1 => 'Cancelados',
        ];

        $counts = Event::select('status_id', DB::raw('COUNT(*) as total'))
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

    private function topEvents(Carbon $start, Carbon $end): array
    {
        $rows = $this->paidSells($start, $end)
            ->select('event_id', DB::raw('SUM(total) as revenue'), DB::raw('SUM(qty) as tickets'))
            ->groupBy('event_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $events = Event::whereIn('id', $rows->pluck('event_id'))
            ->get(['id', 'name', 'image', 'status_id', 'start_date', 'user_id'])
            ->keyBy('id');

        $promotors = User::whereIn('id', $events->pluck('user_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($events, $promotors) {
            $event = $events->get($row->event_id);

            return [
                'event_id' => (int) $row->event_id,
                'name' => $event->name ?? 'Evento removido',
                'image' => $event->image ?? null,
                'status_id' => $event->status_id ?? null,
                'start_date' => $event->start_date ?? null,
                'promotor' => $event ? ($promotors[$event->user_id] ?? null) : null,
                'revenue' => round((float) $row->revenue, 2),
                'tickets' => (int) $row->tickets,
            ];
        })->all();
    }

    private function recentSells(): array
    {
        $sells = Sell::where('status', 1)
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

    private function pendingEvents(): array
    {
        $events = Event::whereIn('status_id', [3, 4])
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'image', 'status_id', 'start_date', 'user_id', 'province_id', 'created_at']);

        $promotors = User::whereIn('id', $events->pluck('user_id')->filter())->pluck('name', 'id');

        return $events->map(function ($event) use ($promotors) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'image' => $event->image,
                'status_id' => $event->status_id,
                'start_date' => $event->start_date,
                'created_at' => $event->created_at,
                'promotor' => $promotors[$event->user_id] ?? null,
            ];
        })->all();
    }

    private function paidSells(Carbon $start, Carbon $end)
    {
        return Sell::where('status', 1)->whereBetween('created_at', [$start, $end]);
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
