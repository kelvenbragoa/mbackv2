<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\CustomerInvite;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotorInviteBatchController extends Controller
{
    use AuthorizesEventAccess;

    public const MAX_QTY = 500;

    public function index(string $inviteId)
    {
        $invite = $this->findInvite($inviteId);
        if ($invite instanceof \Illuminate\Http\JsonResponse) {
            return $invite;
        }

        $batches = CustomerInvite::query()
            ->where('invite_id', $invite->id)
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id')
            ->selectRaw('MIN(name) as name')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('COUNT(*) as issued')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as used_count', [CustomerInvite::STATUS_USED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as unused_count', [CustomerInvite::STATUS_VALID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as returned_count', [CustomerInvite::STATUS_RETURNED])
            ->groupBy('batch_id')
            ->orderByDesc('batch_id')
            ->get()
            ->map(fn ($row) => $this->serializeBatch($row));

        return response()->json([
            'batches' => $batches,
            'max_qty' => self::MAX_QTY,
        ]);
    }

    public function store(Request $request, string $inviteId)
    {
        set_time_limit(180);

        $invite = $this->findInvite($inviteId);
        if ($invite instanceof \Illuminate\Http\JsonResponse) {
            return $invite;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QTY],
        ]);

        $qty = (int) $data['qty'];
        $name = trim($data['name']);

        $batchId = DB::transaction(function () use ($invite, $qty, $name) {
            $batchId = CustomerInvite::nextBatchId();
            CustomerInvite::createBatch($invite->id, (int) $invite->event_id, $name, $qty, $batchId);

            return $batchId;
        });

        return response()->json([
            'batch' => $this->loadBatch($invite->id, $batchId),
        ], 201);
    }

    public function returnUnused(Request $request, string $inviteId, string $batchId)
    {
        $invite = $this->findInvite($inviteId);
        if ($invite instanceof \Illuminate\Http\JsonResponse) {
            return $invite;
        }

        $unused = CustomerInvite::query()
            ->where('invite_id', $invite->id)
            ->where('batch_id', $batchId)
            ->where('status', CustomerInvite::STATUS_VALID)
            ->count();

        if ($unused < 1) {
            return response()->json([
                'message' => 'Este lote não tem convites por usar para devolver.',
            ], 422);
        }

        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.$unused],
        ]);

        $qty = isset($data['qty']) ? (int) $data['qty'] : $unused;

        $returned = DB::transaction(function () use ($invite, $batchId, $qty) {
            $details = CustomerInvite::query()
                ->where('invite_id', $invite->id)
                ->where('batch_id', $batchId)
                ->where('status', CustomerInvite::STATUS_VALID)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($qty)
                ->get();

            foreach ($details as $detail) {
                $detail->update([
                    'status' => CustomerInvite::STATUS_RETURNED,
                ]);
            }

            return $details->count();
        });

        return response()->json([
            'batch' => $this->loadBatch($invite->id, (int) $batchId),
            'returned' => $returned,
        ]);
    }

    public function invites(string $inviteId, string $batchId)
    {
        $invite = $this->findInvite($inviteId);
        if ($invite instanceof \Illuminate\Http\JsonResponse) {
            return $invite;
        }

        $invite->load(['event.province', 'event.city']);

        $details = CustomerInvite::query()
            ->where('invite_id', $invite->id)
            ->where('batch_id', $batchId)
            ->where('status', CustomerInvite::STATUS_VALID)
            ->orderBy('id')
            ->get(['id', 'invite_number', 'qrcode', 'status', 'name', 'event_id', 'batch_id']);

        $buyerName = $details->first()?->name
            ?? CustomerInvite::query()
                ->where('invite_id', $invite->id)
                ->where('batch_id', $batchId)
                ->value('name');

        return response()->json([
            'event' => $invite->event ?? \App\Models\Event::with(['province', 'city'])->find($invite->event_id),
            'invite' => $invite->only(['id', 'name', 'description', 'event_id']),
            'buyer_name' => $buyerName,
            'batch_id' => (int) $batchId,
            'invites' => $details,
        ]);
    }

    private function findInvite(string $inviteId)
    {
        $invite = Invite::find($inviteId);
        if (! $invite) {
            return response()->json(['message' => 'Convite não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($invite->event_id)) {
            return $denied;
        }

        return $invite;
    }

    private function loadBatch(int $inviteId, int $batchId): ?array
    {
        $row = CustomerInvite::query()
            ->where('invite_id', $inviteId)
            ->where('batch_id', $batchId)
            ->selectRaw('batch_id')
            ->selectRaw('MIN(name) as name')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('COUNT(*) as issued')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as used_count', [CustomerInvite::STATUS_USED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as unused_count', [CustomerInvite::STATUS_VALID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as returned_count', [CustomerInvite::STATUS_RETURNED])
            ->groupBy('batch_id')
            ->first();

        return $row ? $this->serializeBatch($row) : null;
    }

    private function serializeBatch($row): array
    {
        return [
            'id' => (int) $row->batch_id,
            'name' => $row->name,
            'issued' => (int) $row->issued,
            'used' => (int) $row->used_count,
            'unused' => (int) $row->unused_count,
            'returned' => (int) $row->returned_count,
            'created_at' => $row->created_at,
        ];
    }
}
