<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\Sell;
use App\Models\Ticket;
use Illuminate\Http\Request;

class PromotorTicketController extends Controller
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

        $ticket = Ticket::create([
            'name'=>$data['name'],
            'price'=>$data['price'],
            'price_aoa' => $this->normalizePriceAoa($data['price_aoa'] ?? null),
            'description'=>$data['description'],
            'event_id'=>$data['event_id'],
            'start_date'=>date('Y-m-d',strtotime($data['start_date'])),
            'end_date'=>date('Y-m-d',strtotime($data['end_date'])),
            'start_time'=>$data['start_time'],
            'end_time'=>$data['end_time'],
            'max_qtd'=>$data['max_qtd'],
            'max_per_order' => (isset($data['max_per_order']) && $data['max_per_order'] !== '' && $data['max_per_order'] !== null)
                ? (int) $data['max_per_order']
                : null,
            'is_package'=>0,
            'is_live' => filter_var($data['is_live'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'valid_for_days' => $this->normalizeValidForDays(
                $data['valid_for_days'] ?? null,
                filter_var($data['is_live'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ),

        ]);

        return response()->json($ticket);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $ticket = Ticket::with('formFields')->withCount('stockSells as sells_count')->find($id);
        if ($ticket) {
            $ticket->available_quantity = $ticket->availableQuantity();
        }

        return response()->json(["ticket"=>$ticket]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $ticket = Ticket::with('formFields')->find($id);
        return response()->json(["ticket"=>$ticket]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $data = $request->all();
        $ticket = Ticket::find($id);

        $data['start_date'] = date('Y-m-d',strtotime($data['start_date']));
        $data['end_date'] = date('Y-m-d',strtotime($data['end_date']));

        if (array_key_exists('max_per_order', $data)) {
            $data['max_per_order'] = ($data['max_per_order'] === '' || $data['max_per_order'] === null)
                ? null
                : (int) $data['max_per_order'];
        }

        if (array_key_exists('is_live', $data)) {
            $data['is_live'] = filter_var($data['is_live'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (array_key_exists('price_aoa', $data)) {
            $data['price_aoa'] = $this->normalizePriceAoa($data['price_aoa']);
        }

        $isLive = array_key_exists('is_live', $data)
            ? (bool) $data['is_live']
            : (bool) $ticket->is_live;

        if (array_key_exists('valid_for_days', $data) || array_key_exists('is_live', $data)) {
            $data['valid_for_days'] = $this->normalizeValidForDays(
                $data['valid_for_days'] ?? ($isLive ? $ticket->valid_for_days : null),
                $isLive
            );
        }

        $ticket->update($data);
        return response()->json([
            "ticket"=>$ticket
        ]);
    }

    private function normalizePriceAoa(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }

    private function normalizeValidForDays(mixed $value, bool $isLive): ?int
    {
        if (! $isLive || $value === null || $value === '') {
            return null;
        }

        $days = (int) $value;

        return $days > 0 ? $days : null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $ticket = Ticket::findOrFail($id);

        $sell = Sell::where('ticket_id',$ticket->id)->get();

        if(count($sell) == 0){
            $ticket->delete();
            return response()->noContent();

        }else{
            return abort(404,"Erro");
        }
    }
}
