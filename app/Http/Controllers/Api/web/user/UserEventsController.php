<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\Category;
use App\Models\Event;
use App\Models\Province;
use App\Support\LiveTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserEventsController extends Controller
{
    use PaginatesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $payload = Cache::remember(
            'web.eventos.'.md5(json_encode($request->query())),
            30,
            function () use ($request) {
                $provinces = Province::orderBy('name', 'asc')->get();
                $categories = Category::orderBy('name', 'asc')->get();

                $events = Event::query()
                    ->withPublicCatalog()
                    ->where('status_id', 2)
                    ->when($request->filled('search'), function ($query) use ($request) {
                        $search = $request->query('search');
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('address', 'like', "%{$search}%");
                        });
                    })
                    ->when($request->filled('province_id'), function ($query) use ($request) {
                        $query->where('province_id', $request->query('province_id'));
                    })
                    ->when($request->filled('category_id'), function ($query) use ($request) {
                        $query->where('main_category_id', $request->query('category_id'));
                    })
                    ->orderBy('end_date', 'desc')
                    ->paginate($this->perPage($request))
                    ->appends($request->query());

                return [
                    'provinces' => $provinces->toArray(),
                    'categories' => $categories->toArray(),
                    'events' => $events->toArray(),
                ];
            }
        );

        return response()->json($payload);
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
    public function show(Request $request, string $id)
    {
        $event = Event::with([
                'user:id,name,company_name,slug,email,mobile,image',
                'province:id,name',
                'city:id,name',
                'tickets',
                'lineups',
                'type:id,name',
                'category:id,name',
                'shopProducts' => function ($query) {
                    $query->active()->with('variants');
                },
            ])
            ->withCount('like')
            ->where(function ($query) use ($id) {
                $query->where('slug', $id)->orWhere('id', $id);
            })
            ->first();

        if (!$event) {
            return response()->json([
                'error' => 'Evento não encontrado'
            ], 404);
        }

        $event->setRelation(
            'shopProducts',
            $event->shopProducts->map->toPublicArray()->values()
        );

        $recommendedBase = Event::query()
            ->where('status_id', 2)
            ->where('id', '!=', $event->id);
        $recommendedTotal = (clone $recommendedBase)->count();
        $recommendedOffset = $recommendedTotal > 4 ? random_int(0, $recommendedTotal - 4) : 0;

        $recommended = (clone $recommendedBase)
            ->withPublicCatalog()
            ->orderByDesc('end_date')
            ->offset($recommendedOffset)
            ->limit(4)
            ->get();

        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user();

        return response()->json([
            'events' => $event,
            'recommended' => $recommended,
            'live_access' => LiveTicketAccess::accessPayload($event, $user),
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
}
