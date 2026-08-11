<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Province;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminEventsController extends Controller
{
    public function index(Request $request)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $events = Event::query()
            ->when($request->query('query'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status_id'), function ($query, $statusId) {
                $query->where('status_id', $statusId);
            })
            ->when($request->query('province_id'), function ($query, $provinceId) {
                $query->where('province_id', $provinceId);
            })
            ->with(['city', 'province', 'category', 'status', 'type', 'user'])
            ->withCount('sell_details as tickets_sold')
            ->withSum(['sells as revenue' => function ($query) {
                $query->where('status', 1);
            }], 'total')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'event' => $events,
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
            'statuses' => Status::orderBy('id')->get(['id', 'name']),
            'summary' => $this->summary(),
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $data = $request->validate([
            'status_id' => 'required|integer|exists:statuses,id',
        ]);

        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        $event->update(['status_id' => $data['status_id']]);

        return response()->json([
            'message' => 'Estado do evento atualizado.',
            'event' => $event->load(['status', 'province', 'city', 'category', 'type', 'user']),
            'summary' => $this->summary(),
        ]);
    }

    private function summary(): array
    {
        $counts = Event::select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        return [
            'total' => (int) $counts->sum(),
            'approved' => (int) ($counts[2] ?? 0),
            'pending' => (int) ($counts[3] ?? 0),
            'review' => (int) ($counts[4] ?? 0),
            'canceled' => (int) ($counts[1] ?? 0),
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
