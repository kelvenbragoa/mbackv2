<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class UserEventsController extends Controller
{
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
    public function show(string $id)
    {
        //
        // Procurar por slug primeiro, depois por ID se não encontrar
        $event = Event::with('user')->with('province')->with('city')->with('tickets')->with('like')->with('lineups')->with('type')
                     ->where('slug', $id)
                     ->orWhere('id', $id)
                     ->first();
        
        // Se não encontrar o evento, retornar erro
        if (!$event) {
            return response()->json([
                "error" => "Evento não encontrado"
            ], 404);
        }
        
        $event_recomended = Event::where('status_id',2)->inRandomOrder()->limit(4)->get();

        return response()->json([
            "events"=>$event,
            "recommended"=>$event_recomended
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
