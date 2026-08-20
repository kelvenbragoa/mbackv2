<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\SellDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminTicketsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $tickets = SellDetails::query()
            ->when($request->query('query'), function ($query, $searchQuery) {
                $query->where(function ($inner) use ($searchQuery) {
                    $inner->where('id', 'like', "%{$searchQuery}%")
                        ->orWhere('ticket_number', 'like', "%{$searchQuery}%")
                        ->orWhere('name', 'like', "%{$searchQuery}%")
                        ->orWhere('email', 'like', "%{$searchQuery}%")
                        ->orWhere('mobile', 'like', "%{$searchQuery}%")
                        ->orWhereHas('event', function ($event) use ($searchQuery) {
                            $event->where('name', 'like', "%{$searchQuery}%");
                        });
                });
            })
            ->when($request->query('status') !== null && $request->query('status') !== '', function ($query) use ($request) {
                $query->where('status', (int) $request->query('status'));
            })
            ->when($request->query('event_id'), function ($query, $eventId) {
                $query->where('event_id', $eventId);
            })
            ->with('event:id,name,start_date')
            ->with('sell.transaction')
            ->with('ticket:id,name,price')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'tickets' => $tickets,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $ticket = SellDetails::with('event.province')
            ->with('sell.transaction')
            ->with('ticket')
            ->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    private function summary(): array
    {
        $counts = SellDetails::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'valid' => (int) ($counts[1] ?? 0),
            'used' => (int) ($counts[0] ?? 0),
        ];
    }

    private function denyNonAdmin()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a esta área.'], 403);
        }

        return null;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        return in_array($perPage, [10, 20, 50], true) ? $perPage : 20;
    }
}
