<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\SellDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTicketsController extends Controller
{
    use PaginatesRequests;

    /**
     * List authenticated user's tickets.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Utilizador não autenticado',
            ], 401);
        }

        $query = SellDetails::with(['event.province', 'event.city', 'ticket', 'sell'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('sell', function ($sellQuery) use ($user) {
                        $sellQuery->where('user_id', $user->id);
                    });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($inner) use ($search) {
                    $inner->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('event', function ($eventQuery) use ($search) {
                            $eventQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->query('status');
                $today = now()->toDateString();

                if ($status === 'used') {
                    $q->where('status', 1);
                } elseif ($status === 'upcoming') {
                    $q->where('status', '!=', 1)
                        ->whereHas('event', function ($eventQuery) use ($today) {
                            $eventQuery->whereDate('end_date', '>=', $today);
                        });
                } elseif ($status === 'expired') {
                    $q->where('status', '!=', 1)
                        ->whereHas('event', function ($eventQuery) use ($today) {
                            $eventQuery->whereDate('end_date', '<', $today);
                        });
                }
            })
            ->orderByDesc('id');

        $tickets = (clone $query)->paginate($this->perPage($request))->appends($request->query());

        $baseQuery = SellDetails::query()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('sell', function ($sellQuery) use ($user) {
                    $sellQuery->where('user_id', $user->id);
                });
        });

        $today = now()->toDateString();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'upcoming' => (clone $baseQuery)->where('status', '!=', 1)
                ->whereHas('event', fn ($e) => $e->whereDate('end_date', '>=', $today))
                ->count(),
            'used' => (clone $baseQuery)->where('status', 1)->count(),
            'expired' => (clone $baseQuery)->where('status', '!=', 1)
                ->whereHas('event', fn ($e) => $e->whereDate('end_date', '<', $today))
                ->count(),
        ];

        return response()->json([
            'tickets' => $tickets,
            'summary' => $summary,
        ]);
    }

    /**
     * Show a single ticket detail for the authenticated user.
     */
    public function show(string $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Utilizador não autenticado',
            ], 401);
        }

        $ticket = SellDetails::with(['event.province', 'event.city', 'ticket', 'sell'])
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('sell', function ($sellQuery) use ($user) {
                        $sellQuery->where('user_id', $user->id);
                    });
            })
            ->first();

        if (!$ticket) {
            return response()->json([
                'error' => 'Bilhete não encontrado',
            ], 404);
        }

        return response()->json([
            'ticket' => $ticket,
        ]);
    }
}
