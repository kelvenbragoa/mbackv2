<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Support\TicketPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PromotorTicketBatchController extends Controller
{
    use AuthorizesEventAccess;

    public const SIZES = [100, 200, 300, 400, 500];

    public function index(string $ticketId)
    {
        $ticket = $this->findTicket($ticketId);
        if ($ticket instanceof \Illuminate\Http\JsonResponse) {
            return $ticket;
        }

        $batches = Sell::query()
            ->where('ticket_id', $ticket->id)
            ->whereHas('transaction', fn ($query) => $query->where('method', 'lote'))
            ->with('transaction')
            ->withCount([
                'selldetails as issued_count',
                'selldetails as used_count' => fn ($query) => $query->where('status', SellDetails::STATUS_USED),
                'selldetails as unused_count' => fn ($query) => $query->where('status', SellDetails::STATUS_VALID),
                'selldetails as returned_count' => fn ($query) => $query->where('status', SellDetails::STATUS_RETURNED),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sell $sell) => $this->serializeBatch($sell));

        return response()->json([
            'batches' => $batches,
            'available_quantity' => $ticket->availableQuantity(),
            'sizes' => self::SIZES,
        ]);
    }

    public function store(Request $request, string $ticketId)
    {
        set_time_limit(180);

        $ticket = $this->findTicket($ticketId);
        if ($ticket instanceof \Illuminate\Http\JsonResponse) {
            return $ticket;
        }

        if ((int) $ticket->is_live === 1) {
            return response()->json([
                'message' => 'Bilhetes live não podem ser gerados em lote para venda física.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'qty' => ['required', 'integer', Rule::in(self::SIZES)],
        ]);

        $qty = (int) $data['qty'];
        $name = trim($data['name']);
        $email = $data['email'] ?? null;
        $mobile = $data['mobile'] ?? null;
        $issuerId = Auth::id();

        $sell = DB::transaction(function () use ($ticket, $qty, $name, $email, $mobile, $issuerId) {
            $locked = Ticket::where('id', $ticket->id)->lockForUpdate()->first();
            $available = $locked->availableQuantity();

            if ($qty > $available) {
                abort(422, "Stock insuficiente. Disponível: {$available}.");
            }

            $sell = Sell::create([
                'event_id' => $ticket->event_id,
                'ticket_id' => $ticket->id,
                'qty' => $qty,
                'price' => $ticket->price,
                'total' => $ticket->price * $qty,
                'status' => 1,
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'user_id' => 0,
            ]);

            Transaction::create([
                'sell_id' => $sell->id,
                'reference' => 'LOTE-'.$sell->id,
                'method' => 'lote',
                'user_id' => $issuerId,
            ]);

            for ($i = 0; $i < $qty; $i++) {
                SellDetails::create([
                    'sell_id' => $sell->id,
                    'event_id' => $ticket->event_id,
                    'ticket_id' => $ticket->id,
                    'status' => SellDetails::STATUS_VALID,
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'user_id' => 0,
                ]);
            }

            return $sell;
        });

        $sell->load('transaction');
        $sell->loadCount([
            'selldetails as issued_count',
            'selldetails as used_count' => fn ($query) => $query->where('status', SellDetails::STATUS_USED),
            'selldetails as unused_count' => fn ($query) => $query->where('status', SellDetails::STATUS_VALID),
            'selldetails as returned_count' => fn ($query) => $query->where('status', SellDetails::STATUS_RETURNED),
        ]);

        return response()->json([
            'batch' => $this->serializeBatch($sell),
            'available_quantity' => $ticket->fresh()->availableQuantity(),
        ], 201);
    }

    public function returnUnused(Request $request, string $ticketId, string $sellId)
    {
        $ticket = $this->findTicket($ticketId);
        if ($ticket instanceof \Illuminate\Http\JsonResponse) {
            return $ticket;
        }

        $sell = $this->findBatch($ticket, $sellId);
        if ($sell instanceof \Illuminate\Http\JsonResponse) {
            return $sell;
        }

        $unused = SellDetails::where('sell_id', $sell->id)
            ->where('status', SellDetails::STATUS_VALID)
            ->count();

        if ($unused < 1) {
            return response()->json([
                'message' => 'Este lote não tem bilhetes por usar para devolver.',
            ], 422);
        }

        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.$unused],
        ]);

        $qty = isset($data['qty']) ? (int) $data['qty'] : $unused;

        $returned = DB::transaction(function () use ($sell, $qty) {
            $details = SellDetails::where('sell_id', $sell->id)
                ->where('status', SellDetails::STATUS_VALID)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($qty)
                ->get();

            foreach ($details as $detail) {
                $detail->update([
                    'status' => SellDetails::STATUS_RETURNED,
                ]);
            }

            $remaining = SellDetails::where('sell_id', $sell->id)
                ->consumingStock()
                ->count();

            $sell->update([
                'qty' => $remaining,
                'total' => $sell->price * $remaining,
            ]);

            return $details->count();
        });

        $sell->refresh();
        $sell->load('transaction');
        $sell->loadCount([
            'selldetails as issued_count',
            'selldetails as used_count' => fn ($query) => $query->where('status', SellDetails::STATUS_USED),
            'selldetails as unused_count' => fn ($query) => $query->where('status', SellDetails::STATUS_VALID),
            'selldetails as returned_count' => fn ($query) => $query->where('status', SellDetails::STATUS_RETURNED),
        ]);

        return response()->json([
            'batch' => $this->serializeBatch($sell),
            'available_quantity' => $ticket->fresh()->availableQuantity(),
            'returned' => $returned,
        ]);
    }

    public function tickets(string $ticketId, string $sellId)
    {
        $ticket = $this->findTicket($ticketId);
        if ($ticket instanceof \Illuminate\Http\JsonResponse) {
            return $ticket;
        }

        $sell = $this->findBatch($ticket, $sellId);
        if ($sell instanceof \Illuminate\Http\JsonResponse) {
            return $sell;
        }

        $sell->load(['event.province', 'event.city', 'ticket']);

        $details = SellDetails::query()
            ->where('sell_id', $sell->id)
            ->where('status', SellDetails::STATUS_VALID)
            ->orderBy('id')
            ->get(['id', 'ticket_number', 'qrcode', 'status', 'name', 'event_id']);

        return response()->json([
            'event' => $sell->event,
            'ticket' => $sell->ticket,
            'buyer_name' => $sell->name,
            'price' => (float) $sell->price,
            'batch_id' => $sell->id,
            'tickets' => $details,
        ]);
    }

    public function pdf(Request $request, string $ticketId, string $sellId)
    {
        $ticket = $this->findTicket($ticketId);
        if ($ticket instanceof \Illuminate\Http\JsonResponse) {
            return $ticket;
        }

        $sell = $this->findBatch($ticket, $sellId);
        if ($sell instanceof \Illuminate\Http\JsonResponse) {
            return $sell;
        }

        $layout = $request->query('layout', 'a4');
        if (! in_array($layout, ['a4', 'boca'], true)) {
            $layout = 'a4';
        }

        set_time_limit(180);

        $pdf = TicketPdf::forBatchSellId((int) $sell->id, $layout);
        if (! $pdf) {
            return response()->json([
                'message' => 'Não há bilhetes válidos neste lote para imprimir.',
            ], 422);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $sell->name) ?: 'lote';

        return $pdf->download('lote-'.$sell->id.'-'.$safeName.'-'.$layout.'.pdf');
    }

    private function findTicket(string $ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (! $ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }
        if ($denied = $this->denyEventAccess($ticket->event_id)) {
            return $denied;
        }

        return $ticket;
    }

    private function findBatch(Ticket $ticket, string $sellId)
    {
        $sell = Sell::where('ticket_id', $ticket->id)
            ->whereHas('transaction', fn ($query) => $query->where('method', 'lote'))
            ->with('transaction')
            ->find($sellId);

        if (! $sell) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        return $sell;
    }

    private function serializeBatch(Sell $sell): array
    {
        $issued = (int) ($sell->issued_count ?? 0);
        $used = (int) ($sell->used_count ?? 0);
        $unused = (int) ($sell->unused_count ?? 0);
        $returned = (int) ($sell->returned_count ?? 0);

        return [
            'id' => $sell->id,
            'name' => $sell->name,
            'email' => $sell->email,
            'mobile' => $sell->mobile,
            'qty' => (int) $sell->qty,
            'price' => (float) $sell->price,
            'total' => (float) $sell->total,
            'channel' => $sell->saleChannel(),
            'method' => $sell->transaction?->method ?: 'lote',
            'issued' => $issued,
            'used' => $used,
            'unused' => $unused,
            'returned' => $returned,
            'created_at' => $sell->created_at,
        ];
    }
}
