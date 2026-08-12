<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\CardTransaction;
use App\Models\EventCard;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    /**
     * Acquire a MySQL named lock. Returns false when another request holds it.
     */
    protected function acquireLock(string $name, int $timeoutSeconds = 10): bool
    {
        $got = collect(DB::select('SELECT GET_LOCK(?, ?) AS l', [$name, $timeoutSeconds]))->first()->l ?? 0;

        return (int) $got === 1;
    }

    protected function releaseLock(string $name): void
    {
        DB::select('SELECT RELEASE_LOCK(?)', [$name]);
    }

    public function refund($id, $userid)
    {
        $cardId = (int) $id;
        $userId = (int) $userid;
        $lockName = 'barman-card-'.$cardId;

        if (! $this->acquireLock($lockName)) {
            return response([
                'card' => [],
                'message' => 'Operação já em processamento neste cartão. Aguarde.',
                'code' => 'card_busy',
            ], 409);
        }

        try {
            return DB::transaction(function () use ($cardId, $userId) {
                $card = EventCard::with('user')->where('id', $cardId)->lockForUpdate()->first();

                if (! $card) {
                    return response([
                        'card' => [],
                        'message' => 'Cartão não encontrado.',
                        'code' => 'card_not_found',
                    ], 404);
                }

                if ((int) $card->status === 0) {
                    return response([
                        'card' => [$card],
                        'message' => 'Este cartão já foi devolvido.',
                        'code' => 'already_refunded',
                    ], 409);
                }

                $refund = Refund::where('event_card_id', $cardId)
                    ->where('status', 0)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $refund) {
                    return response([
                        'card' => [$card],
                        'message' => 'Não há caução pendente para devolver neste cartão.',
                        'code' => 'refund_unavailable',
                    ], 422);
                }

                $recentRefundTx = CardTransaction::where('event_card_id', $cardId)
                    ->where('type_of_transaction_id', 2)
                    ->orderByDesc('id')
                    ->first();

                if ($recentRefundTx && abs(now()->diffInRealSeconds($recentRefundTx->created_at)) < 15) {
                    return response([
                        'card' => [$card],
                        'message' => 'Devolução duplicada bloqueada. Já houve uma devolução há poucos segundos.',
                        'code' => 'duplicate_refund',
                    ], 409);
                }

                $balance = (int) $card->balance;

                CardTransaction::create([
                    'card_id' => $card->id,
                    'event_card_id' => $card->id,
                    'event_id' => $card->event_id,
                    'sell_id' => 0,
                    'total' => $balance,
                    'balance' => 0,
                    'type_of_transaction_id' => 2,
                    'user_id' => $userId,
                ]);

                $card->update([
                    'balance' => 0,
                    'status' => 0,
                ]);

                $refund->update([
                    'status' => 1,
                ]);

                $card->refresh()->load('user');

                return response([
                    'card' => [$card],
                    'message' => 'Devolução feita com sucesso',
                ], 200);
            });
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function registerCard($id, $userid)
    {
        $card = EventCard::create([
            'name' => 'USER',
            'event_id' => $id,
            'status' => 0,
            'card_id' => 0,
            'balance' => 0,
            'user_id' => $userid,
        ]);

        $newcard = EventCard::with('user')->find($card->id);

        return response([
            'card' => [$newcard],
        ], 200);
    }

    public function viewCard($id)
    {
        $card = EventCard::where('id', $id)->with('user')->get();
        $transactions = CardTransaction::where('card_id', $id)->orderBy('id', 'desc')->get();

        return response([
            'card' => $card,
            'transactions' => $transactions,
        ], 200);
    }

    public function topUpCard($id, $top, $userid)
    {
        $cardId = (int) $id;
        $amount = (int) $top;
        $userId = (int) $userid;

        if ($amount <= 0) {
            return response([
                'card' => [],
                'message' => 'Valor de recarga inválido.',
                'code' => 'invalid_amount',
            ], 422);
        }

        $lockName = 'barman-card-'.$cardId;

        if (! $this->acquireLock($lockName)) {
            return response([
                'card' => [],
                'message' => 'Recarga já em processamento neste cartão. Aguarde e verifique o saldo.',
                'code' => 'card_busy',
            ], 409);
        }

        try {
            return DB::transaction(function () use ($cardId, $amount, $userId) {
                $card = EventCard::with('user')->where('id', $cardId)->lockForUpdate()->first();

                if (! $card) {
                    return response([
                        'card' => [],
                        'message' => 'Cartão não encontrado.',
                        'code' => 'card_not_found',
                    ], 404);
                }

                $anyTransaction = CardTransaction::where('event_card_id', $cardId)
                    ->orderByDesc('id')
                    ->first();

                // After refund (status 0) do not allow new top-ups.
                if ($anyTransaction && (int) $card->status === 0) {
                    return response([
                        'card' => [$card],
                        'message' => 'Não foi possível carregar porque o cartão já foi devolvido.',
                        'code' => 'card_refunded',
                    ], 422);
                }

                $lastTopUp = CardTransaction::where('event_card_id', $cardId)
                    ->where('type_of_transaction_id', 0)
                    ->orderByDesc('id')
                    ->first();

                if (
                    $lastTopUp
                    && (int) $lastTopUp->total === $amount
                    && abs(now()->diffInRealSeconds($lastTopUp->created_at)) < 15
                ) {
                    return response([
                        'card' => [$card],
                        'message' => 'Recarga duplicada bloqueada. Já existe uma recarga idêntica há poucos segundos.',
                        'code' => 'duplicate_topup',
                        'balance' => $card->balance,
                    ], 409);
                }

                // First ever top-up: withhold 100 MT deposit.
                if ($anyTransaction === null) {
                    if ($amount < 100) {
                        return response([
                            'card' => [$card],
                            'message' => 'A primeira recarga deve ser de pelo menos 100 MT (caução).',
                            'code' => 'min_first_topup',
                        ], 422);
                    }

                    $usable = $amount - 100;

                    $cardTransaction = CardTransaction::create([
                        'card_id' => $card->id,
                        'event_card_id' => $card->id,
                        'event_id' => $card->event_id,
                        'sell_id' => 0,
                        'total' => $amount,
                        'balance' => $usable,
                        'type_of_transaction_id' => 0,
                        'user_id' => $userId,
                    ]);

                    $card->update([
                        'balance' => $usable,
                        'status' => 1,
                    ]);

                    Refund::create([
                        'user_id' => $userId,
                        'event_card_id' => $card->id,
                        'event_id' => $card->event_id,
                        'card_transaction_id' => $cardTransaction->id,
                        'total' => $amount,
                        'status' => 0,
                        'refund' => 100,
                        'balance' => $usable,
                    ]);

                    $card->refresh()->load('user');

                    return response([
                        'card' => [$card],
                        'message' => 'Cartão recarregado com '.$amount.'MT. Saldo atual '.$usable.'MT. Taxa de caução 100 MT',
                    ], 200);
                }

                $newBalance = (int) $card->balance + $amount;

                CardTransaction::create([
                    'card_id' => $card->id,
                    'event_card_id' => $card->id,
                    'event_id' => $card->event_id,
                    'sell_id' => 0,
                    'total' => $amount,
                    'balance' => $newBalance,
                    'type_of_transaction_id' => 0,
                    'user_id' => $userId,
                ]);

                $card->update([
                    'balance' => $newBalance,
                    'status' => 1,
                ]);

                $card->refresh()->load('user');

                return response([
                    'card' => [$card],
                    'message' => 'Cartão recarregado com '.$amount.'MT. Saldo atual '.$newBalance.'MT',
                ], 200);
            });
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store()
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
