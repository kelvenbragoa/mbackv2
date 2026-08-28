<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id){

        $event = Event::find($id);

        $products = Ticket::where('event_id', $event->id)
            ->orderBy('name', 'asc')
            ->get()
            ->transform(function ($item) {
                $item->available_quantity = max(0, (int) $item->max_qtd);
                return $item;
            });

        return response([
            'products' => $products,
        ],200);

    }

    public function productdetail(Request $request, $id)
    {
        $ticket = Ticket::find($id);
        if (! $ticket) {
            return response([
                'product' => [],
            ], 200);
        }

        $available = max(0, (int) $ticket->max_qtd);
        $inCart = 0;
        if ($request->filled('protocol_id')) {
            $inCart = (int) Carts::where('protocol_id', $request->protocol_id)
                ->where('ticket_id', $id)
                ->whereNull('sell_id')
                ->sum('qtd');
        }

        $ticket->available_quantity = $available;
        $ticket->can_add = max(0, $available - $inCart);

        return response([
            'product' => [$ticket],
        ], 200);
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
