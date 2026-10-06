<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\CustomerInvite;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotorInviteController extends Controller
{
    use AuthorizesEventAccess;

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
     * Optional qty creates a physical invite batch (1–500).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.PromotorInviteBatchController::MAX_QTY],
        ]);

        if ($denied = $this->denyEventAccess($data['event_id'])) {
            return $denied;
        }

        $name = trim($data['name']);
        $qty = isset($data['qty']) ? (int) $data['qty'] : null;

        $result = DB::transaction(function () use ($data, $name, $qty) {
            $invite = Invite::create([
                'name' => $name,
                'description' => $data['description'],
                'event_id' => $data['event_id'],
            ]);

            $batch = null;
            if ($qty) {
                $batchId = CustomerInvite::nextBatchId();
                CustomerInvite::createBatch($invite->id, (int) $invite->event_id, $name, $qty, $batchId);
                $batch = [
                    'id' => $batchId,
                    'name' => $name,
                    'issued' => $qty,
                    'used' => 0,
                    'unused' => $qty,
                    'returned' => 0,
                ];
            }

            return compact('invite', 'batch');
        });

        return response()->json([
            'invite' => $result['invite'],
            'batch' => $result['batch'],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $invite = Invite::find($id);
        if (! $invite) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($invite->event_id)) {
            return $denied;
        }

        $customer = CustomerInvite::where('invite_id', $invite->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'invite' => $invite,
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $invite = Invite::find($id);
        if (! $invite) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($invite->event_id)) {
            return $denied;
        }

        return response()->json(['invite' => $invite]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invite = Invite::find($id);
        if (! $invite) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($invite->event_id)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:1000'],
        ]);

        $invite->update($data);

        return response()->json([
            'invite' => $invite,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $invite = Invite::findOrFail($id);
        if ($denied = $this->denyEventAccess($invite->event_id)) {
            return $denied;
        }

        $sell = CustomerInvite::where('invite_id', $invite->id)->get();

        if (count($sell) == 0) {
            $invite->delete();

            return response()->noContent();
        }

        return abort(404, 'Erro Ao apagar');
    }
}
