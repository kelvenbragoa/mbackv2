<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvite;
use App\Models\Event;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PromotorCustomerInviteController extends Controller
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

        $invite = CustomerInvite::create($data);
        $customers = CustomerInvite::where('invite_id', $data['invite_id'])->get();

        return response()->json($customers);
    }

    public function storebulk(Request $request){
        $data = $request->all();

        DB::transaction(function () use ($data) {
            for ($i=1; $i <= $data['end']; $i++) { 
            CustomerInvite::create([
                    "name" =>$data["name"].'#'.$i,
                    "event_id" =>$data["event_id"],
                    "invite_id" =>$data["invite_id"],
                    "status" =>$data["status"],
            ]);
            }
        });

        
        $customers = CustomerInvite::where('invite_id', $data['invite_id'])->get();

        return response()->json($customers);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $customer = CustomerInvite::with('invite')->with('event.province')->find($id);
        return response()->json(
            ["customer"=>$customer
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
        $invite = CustomerInvite::findOrFail($id);


            $invite->delete();
            return response()->noContent();

        
    }

    public function downloadinvite($id){

        $customer = CustomerInvite::with('invite')->with('event.province')->find($id);
        $event = Event::find($customer->event_id);

        $pdf = Pdf::loadView('pdf.invite', compact('customer','event'))->setOptions([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => 'true'
        ]);
        return $pdf->setPaper('a4')->download('invite.pdf');
    }
}
