<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\Category;
use App\Models\Event;
use App\Models\Province;
use Illuminate\Http\Request;

class UserCategoriesController extends Controller
{
    use PaginatesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(string $id, Request $request)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'error' => 'Categoria não encontrada',
            ], 404);
        }

        $provinces = Province::orderBy('name', 'asc')->get();

        $events = Event::with(['user', 'province', 'city', 'type'])
            ->withMin('tickets', 'price')
            ->where('status_id', 2)
            ->where('main_category_id', $category->id)
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
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'category' => $category,
            'provinces' => $provinces,
            'events' => $events,
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
