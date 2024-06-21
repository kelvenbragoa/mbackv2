<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\LineUp;
use Illuminate\Http\Request;

class PromotorLineUpsController extends Controller
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
        $data = $request->all();

        $lineup = LineUp::create([
            'name'=>$data['name'],
            'description'=>$data['description'],
            'event_id'=>$data['event_id'],
            'start_time'=>$data['start_time'],
            'end_time'=>$data['end_time'],
        ]);

        return response()->json($lineup);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $lineup = LineUp::find($id);

        return response()->json(["lineup"=>$lineup]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $lineup = LineUp::find($id);
        return response()->json(["lineup"=>$lineup]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $lineup = LineUp::find($id);

        $lineup->update($data);
        return response()->json([
            "lineup"=>$lineup
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $lineup = LineUp::findOrFail($id);
        $lineup->delete();
        return response()->noContent();

      
    }
}
