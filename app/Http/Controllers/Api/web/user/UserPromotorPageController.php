<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

class UserPromotorPageController extends Controller
{
    use PaginatesRequests;

    /**
     * Public directory of promoters with published events.
     */
    public function index(Request $request)
    {
        $promotores = User::query()
            ->where('is_promotor', 1)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('events', function ($query) {
                $query->where('status_id', 2);
            })
            ->withCount([
                'events as events_count' => function ($query) {
                    $query->where('status_id', 2);
                },
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('company_location', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('COALESCE(NULLIF(company_name, ""), name) asc')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        $promotores->getCollection()->transform(function (User $user) {
            return array_merge($user->toPublicPageArray(), [
                'events_count' => (int) $user->events_count,
            ]);
        });

        return response()->json([
            'promotores' => $promotores,
        ]);
    }

    /**
     * Public promoter page: profile + published events.
     */
    public function show(Request $request, string $slug)
    {
        $promotor = User::query()
            ->where('is_promotor', 1)
            ->where('slug', $slug)
            ->first();

        if (! $promotor) {
            return response()->json([
                'error' => 'Promotor não encontrado',
            ], 404);
        }

        $events = Event::with(['province', 'city', 'type'])
            ->withMin('tickets', 'price')
            ->where('user_id', $promotor->id)
            ->where('status_id', 2)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy('end_date', 'desc')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'promotor' => $promotor->toPublicPageArray(),
            'events' => $events,
        ]);
    }
}
