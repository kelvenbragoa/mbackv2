<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserPromotorPageController extends Controller
{
    use PaginatesRequests;

    /**
     * Public directory of promoters with published events.
     */
    public function index(Request $request)
    {
        $payload = Cache::remember(
            'web.promotores.'.md5(json_encode($request->query())),
            30,
            function () use ($request) {
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

                return [
                    'promotores' => $promotores->toArray(),
                ];
            }
        );

        return response()->json($payload);
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

        $payload = Cache::remember(
            'web.promotor.'.$promotor->id.'.'.md5(json_encode($request->query())),
            30,
            function () use ($request, $promotor) {
                $events = Event::query()
                    ->withPublicCatalog()
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

                return [
                    'promotor' => $promotor->toPublicPageArray(),
                    'events' => $events->toArray(),
                ];
            }
        );

        return response()->json($payload);
    }
}
