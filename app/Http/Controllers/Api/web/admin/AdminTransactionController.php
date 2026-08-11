<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Mail\SendTickets;
use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\TemporarySell;
use App\Models\TemporaryTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $transaction = TemporaryTransaction::query()
            ->when($request->query('query'), function ($query, $searchQuery) {
                $query->where(function ($inner) use ($searchQuery) {
                    $inner->where('reference', 'like', "%{$searchQuery}%")
                        ->orWhereHas('sell', function ($sell) use ($searchQuery) {
                            $sell->where('name', 'like', "%{$searchQuery}%")
                                ->orWhere('email', 'like', "%{$searchQuery}%")
                                ->orWhere('mobile', 'like', "%{$searchQuery}%");
                        });
                });
            })
            ->with('sell.selldetails')
            ->with('sell.event')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'transaction' => $transaction,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $transaction = TemporaryTransaction::with('sell.selldetails')
            ->with('sell.event')
            ->find($id);

        if (!$transaction) {
            return response()->json(['message' => 'Transação não encontrada.'], 404);
        }

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * Confirm a pending payment: turns the temporary records into a real sell,
     * issues the tickets and emails them to the buyer.
     */
    public function confirm(string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $temporaryTransaction = TemporaryTransaction::find($id);

        if (!$temporaryTransaction) {
            return response()->json(['message' => 'Transação não encontrada ou já confirmada.'], 404);
        }

        $temporarySell = TemporarySell::find($temporaryTransaction->sell_id);

        if (!$temporarySell) {
            return response()->json(['message' => 'A encomenda associada a esta transação já não existe.'], 404);
        }

        $event = Event::find($temporarySell->event_id);

        if (!$event) {
            return response()->json(['message' => 'O evento desta encomenda já não existe.'], 404);
        }

        try {
            $sell = DB::transaction(function () use ($temporarySell, $temporaryTransaction) {
                $sell = Sell::create([
                    'event_id' => $temporarySell->event_id,
                    'ticket_id' => $temporarySell->ticket_id,
                    'qty' => $temporarySell->qty,
                    'price' => $temporarySell->price,
                    'total' => $temporarySell->price * $temporarySell->qty,
                    'status' => 1,
                    'name' => $temporarySell->name,
                    'email' => $temporarySell->email,
                    'mobile' => $temporarySell->mobile,
                    'user_id' => $temporarySell->user_id ?? null,
                ]);

                Transaction::create([
                    'sell_id' => $sell->id,
                    'reference' => $temporaryTransaction->reference,
                    'method' => $temporaryTransaction->method ?? 'mpesa',
                    'user_id' => $temporarySell->user_id ?? null,
                ]);

                for ($i = 0; $i < $temporarySell->qty; $i++) {
                    SellDetails::create([
                        'sell_id' => $sell->id,
                        'event_id' => $sell->event_id,
                        'ticket_id' => $sell->ticket_id,
                        'status' => 1,
                        'name' => $sell->name,
                        'email' => $sell->email,
                        'mobile' => $sell->mobile,
                        'user_id' => $sell->user_id ?? null,
                    ]);
                }

                $temporarySell->transaction()->delete();
                $temporarySell->selldetails()->delete();
                $temporarySell->delete();

                return $sell;
            });
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Não foi possível confirmar a transação. Nenhuma alteração foi gravada.',
            ], 500);
        }

        $emailSent = $this->sendTickets($sell, $event);

        return response()->json([
            'message' => $emailSent
                ? 'Transação confirmada e bilhetes enviados por email.'
                : 'Transação confirmada, mas não foi possível enviar o email com os bilhetes.',
            'email_sent' => $emailSent,
            'sell_id' => $sell->id,
            'summary' => $this->summary(),
        ]);
    }

    private function sendTickets(Sell $sell, Event $event): bool
    {
        if (!$sell->email) {
            return false;
        }

        $content = "Olá, {$sell->name}. A sua compra para o evento {$event->name} foi realizada com sucesso. Segue o seu bilhete em anexo.";
        $detail = SellDetails::where('sell_id', $sell->id)->get();

        try {
            Mail::to($sell->email)->send(new SendTickets($detail, $event->id, $sell->id, $content));

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    private function summary(): array
    {
        return [
            'pending' => TemporaryTransaction::count(),
            'amount' => (float) TemporarySell::whereIn('id', TemporaryTransaction::select('sell_id'))->sum('total'),
        ];
    }

    private function denyNonAdmin()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a esta área.'], 403);
        }

        return null;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        return in_array($perPage, [10, 20, 50], true) ? $perPage : 20;
    }
}
