<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\Category;
use App\Models\Event;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WelcomePageController extends Controller
{
    use PaginatesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $payload = Cache::remember(
            'web.homepage.'.md5(json_encode($request->query())),
            30,
            function () use ($request) {
                $categories = Category::orderBy('name', 'asc')->get();
                $provinces = Province::orderBy('name', 'asc')->get();

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
                    ->orderBy('end_date', 'desc')
                    ->paginate($this->perPage($request, 9))
                    ->appends($request->query());

                // Hero: evento marcado pelo admin; senão o primeiro da lista actual.
                $heroEvent = Event::query()
                    ->withPublicCatalog()
                    ->where('status_id', 2)
                    ->where('is_featured', true)
                    ->orderByDesc('updated_at')
                    ->first();

                if (! $heroEvent) {
                    $heroEvent = $events->getCollection()->first();
                }

                return [
                    'categories' => $categories->toArray(),
                    'provinces' => $provinces->toArray(),
                    'events' => $events->toArray(),
                    'hero_event' => $heroEvent?->toArray(),
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
    public function show(string $id)
    {
        //
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
