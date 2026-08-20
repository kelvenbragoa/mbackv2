<?php

namespace App\Http\Controllers\Api\mobile\barman;

use App\Http\Controllers\Controller;
use App\Models\Barman;
use App\Models\CardTransaction;
use App\Models\CartBar;
use App\Models\EventCard;
use App\Models\Products;
use App\Models\Refund;
use App\Models\SellBar;
use App\Models\SellDetailBar;
use App\Services\ProductStockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SellController extends Controller
{
    // TIPO DE TRANSACAO 0 TOPUP
    // 1 VENDA
    // 2 DEVOLUCAO.
    public function index($userid)
    {
        return response([
            'sells' => SellBar::where('user_id', $userid)->orderBy('created_at', 'desc')->get(),
        ], 200);
    }

    public function create()
    {
        //
    }

    public function store(Request $request, ProductStockMovementService $stockMovements)
    {
        $data = $request->all();

        if (($data['total'] ?? 0) == 0) {
            return response([
                'message' => 'Nenhuma venda efetuada.',
            ], 403);
        }

        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            return response([
                'message' => 'Utilizador inválido.',
            ], 403);
        }

        // Serialize concurrent sell attempts for the same barman (double-tap / slow network).
        $lockName = 'barman-sell-'.$userId;
        $cardLockName = null;
        $gotLock = collect(DB::select('SELECT GET_LOCK(?, 10) AS l', [$lockName]))->first()->l ?? 0;
        if ((int) $gotLock !== 1) {
            return response([
                'message' => 'Venda já em processamento. Aguarde e verifique Minhas Vendas.',
                'code' => 'sell_in_progress',
            ], 409);
        }

        try {
            $last_sell = SellBar::where('user_id', $userId)
                ->where('event_id', $data['event_id'])
                ->where('total', $data['total'])
                ->where('method', $data['method'])
                ->orderBy('id', 'desc')
                ->first();

            if ($last_sell != null) {
                $seconds = abs(now()->diffInRealSeconds($last_sell->created_at));

                if ($seconds < 15) {
                    return response([
                        'message' => 'Venda duplicada bloqueada. Já existe uma venda idêntica há '.$seconds.' segundos. Verifique Minhas Vendas.',
                        'code' => 'duplicate_sell',
                        'sell_id' => $last_sell->id,
                    ], 409);
                }
            }

            $mycartverfify = CartBar::where('user_id', $userId)->whereNull('sell_id')->get();

            if ($mycartverfify->isEmpty()) {
                return response([
                    'message' => 'Carrinho vazio. A venda pode já ter sido processada.',
                    'code' => 'empty_cart',
                ], 409);
            }

            $products_out_of_stock = 0;

            foreach ($mycartverfify as $item) {
                $product = Products::find($item->product_id);

                if (! $product || $item->qtd > $product->qtd) {
                    $products_out_of_stock = $products_out_of_stock + 1;
                }
            }

            if ($products_out_of_stock > 0) {
                return response([
                    'message' => 'Venda não concluída. Existem '.$products_out_of_stock.' que já está sem Estoque. Apague os produtos e volta a adicionar.',
                ], 422);
            }

            $isCashless = ($data['method'] ?? '') === 'cashless';
            $cardId = $isCashless ? (int) ($data['card_id'] ?? 0) : 0;

            if ($isCashless) {
                if ($cardId <= 0) {
                    return response([
                        'message' => 'Cartão cashless inválido.',
                        'code' => 'invalid_card',
                    ], 422);
                }

                $cardLockName = 'barman-card-'.$cardId;
                $gotCardLock = collect(DB::select('SELECT GET_LOCK(?, 10) AS l', [$cardLockName]))->first()->l ?? 0;
                if ((int) $gotCardLock !== 1) {
                    return response([
                        'message' => 'Cartão em uso (recarga/venda). Aguarde e tente novamente.',
                        'code' => 'card_busy',
                    ], 409);
                }
            }

            try {
                $sellId = DB::transaction(function () use ($data, $stockMovements, $userId, $isCashless, $cardId) {
                    // Lock cart rows so a parallel request cannot process the same items.
                    $mycart = CartBar::with('product')
                        ->where('user_id', $userId)
                        ->whereNull('sell_id')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($mycart->isEmpty()) {
                        throw new InvalidArgumentException('Carrinho vazio ou venda já processada.');
                    }

                    $cardTransaction = null;

                    if ($isCashless) {
                        $card = EventCard::where('id', $cardId)->lockForUpdate()->first();

                        if (! $card) {
                            throw new InvalidArgumentException('Cartão não encontrado.');
                        }

                        if ((int) $card->status === 0) {
                            throw new InvalidArgumentException('Cartão devolvido. Não é possível vender cashless.');
                        }

                        $total = (float) $data['total'];
                        if ($total > (float) $card->balance) {
                            throw new InvalidArgumentException('Venda não concluída. Saldo insuficiente no cartão');
                        }

                        $recentDebit = CardTransaction::where('event_card_id', $card->id)
                            ->where('type_of_transaction_id', 1)
                            ->where('total', $total)
                            ->orderByDesc('id')
                            ->first();

                        if ($recentDebit && abs(now()->diffInRealSeconds($recentDebit->created_at)) < 15) {
                            throw new InvalidArgumentException(
                                'Desconto cashless duplicado bloqueado. Já houve um débito idêntico há poucos segundos.'
                            );
                        }

                        $balanceRemain = (float) $card->balance - $total;
                        $card->update(['balance' => $balanceRemain]);

                        $cardTransaction = CardTransaction::create([
                            'card_id' => $card->id,
                            'event_card_id' => $card->id,
                            'event_id' => $card->event_id,
                            'sell_id' => 0,
                            'total' => $total,
                            'balance' => $balanceRemain,
                            'type_of_transaction_id' => 1,
                            'user_id' => $data['user_id'],
                        ]);
                    }

                    $id = SellBar::create([
                        'user_id' => $data['user_id'],
                        'total' => $data['total'],
                        'method' => $data['method'],
                        'ref' => $data['ref'],
                        'status' => 1,
                        'event_id' => $data['event_id'],
                        'bar_store_id' => $data['bar_store_id'],
                    ])->id;

                    if ($cardTransaction) {
                        $cardTransaction->update([
                            'sell_id' => $id,
                        ]);
                    }

                    foreach ($mycart as $item) {
                        $detail = SellDetailBar::create([
                            'sell_id' => $id,
                            'user_id' => $data['user_id'],
                            'event_id' => $data['event_id'],
                            'product_id' => $item->product_id,
                            'status' => 1,
                            'qtd' => $item->qtd,
                            'price' => $item->product->sell_price,
                            'total' => $item->qtd * $item->product->sell_price,
                            'bar_store_id' => $data['bar_store_id'],
                        ]);

                        $stockMovements->applySale(
                            $item->product,
                            (int) $item->qtd,
                            (int) $data['event_id'],
                            isset($data['bar_store_id']) ? (int) $data['bar_store_id'] : null,
                            (int) $id,
                            (int) $detail->id,
                            'Venda barman #'.$data['user_id'].' · método '.$data['method']
                        );

                        $item->delete();
                    }

                    return $id;
                });
            } catch (InvalidArgumentException $e) {
                return response([
                    'message' => $e->getMessage(),
                    'code' => 'sell_aborted',
                ], 409);
            }

            return response([
                'message' => 'Sua ordem foi efectuada com sucesso. Va até a  Minhas Vendas para visualizar.',
                'sell_id' => $sellId,
            ], 200);
        } finally {
            if (! empty($cardLockName)) {
                DB::select('SELECT RELEASE_LOCK(?)', [$cardLockName]);
            }
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function selldetails($id)
    {
        return response([
            'selldetail' => SellDetailBar::where('sell_id', $id)->with('product:id,name')->with('sell:id,method,total,status')->with('transaction')->get(),
        ], 200);
    }

    public function verifyreceipt($id, $userid)
    {
        $ticket = SellBar::find($id);

        if (! $ticket) {
            return response([
                'message' => 'Recibo não encontrado.',
            ], 404);
        }

        if ((int) $ticket->status === 0 || $ticket->verified_at !== null) {
            return response([
                'message' => 'Este recibo já foi verificado.',
            ], 409);
        }

        $ticket->update([
            'status' => 0,
            'verified_by' => $userid,
            'verified_at' => now(),
        ]);

        return response([
            'message' => 'Recibo Verificado Com sucesso',
        ], 200);
    }

    public function status($id)
    {
        $sellbar = SellBar::find($id);

        if (! $sellbar) {
            return response([
                'message' => 'Recibo não encontrado.',
            ], 404);
        }

        return response([
            'status' => $sellbar->status,
        ], 200);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy($id, $userid, ProductStockMovementService $stockMovements)
    {
        $sell = SellBar::find($id);

        if (! $sell) {
            return response([
                'message' => 'Venda não encontrada',
            ], 403);
        }

        if ($sell->user_id != $userid) {
            return response([
                'message' => 'Permissão negada.',
            ], 403);
        }

        if ((int) $sell->status === 0 || $sell->verified_at !== null) {
            return response([
                'message' => 'Não é possível cancelar um recibo já verificado.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($sell, $id, $stockMovements) {
                $sellbardetail = SellDetailBar::where('sell_id', $id)->get();

                foreach ($sellbardetail as $item) {
                    $product = Products::find($item->product_id);
                    if ($product) {
                        $stockMovements->applySaleCancel(
                            $product,
                            (int) $item->qtd,
                            (int) $item->event_id,
                            $item->bar_store_id ? (int) $item->bar_store_id : (int) $sell->bar_store_id,
                            (int) $sell->id,
                            'Cancelamento venda #'.$sell->id.' · barman #'.$sell->user_id
                        );
                    }

                    $item->delete();
                }

                if ($sell->method == 'cashless') {
                    $transaction = CardTransaction::where('sell_id', $sell->id)->first();
                    if ($transaction) {
                        $card = EventCard::find($transaction->event_card_id);
                        if ($card) {
                            $card->update([
                                'balance' => $card->balance + $sell->total,
                            ]);
                        }

                        $transactions = CardTransaction::where('sell_id', $sell->id)->get();
                        foreach ($transactions as $tx) {
                            $tx->delete();
                        }
                    }
                }

                $sell->delete();
            });
        } catch (InvalidArgumentException $e) {
            return response([
                'message' => $e->getMessage(),
            ], 403);
        }

        return response([
            'message' => 'Venda apagada com sucesso!',
        ], 200);
    }

    public function operation($id)
    {
        $barman = Barman::find($id);
        $sells_verified = SellBar::where('verified_by', $id)->get();
        $sells_made = SellBar::where('user_id', $id)->get();

        $sells_made_dinheiro = SellBar::where('user_id', $id)->where('method', 'dinheiro')->get();
        $sells_made_cartao = SellBar::where('user_id', $id)->where('method', 'cartao')->get();
        $sells_made_mpesa = SellBar::where('user_id', $id)->where('method', 'mpesa')->get();
        $sells_made_emola = SellBar::where('user_id', $id)->where('method', 'emola')->get();
        $sells_made_cashless = SellBar::where('user_id', $id)->where('method', 'cashless')->get();

        $event_cards_registered = EventCard::where('user_id', $id)->get();
        $event_cards_active = EventCard::where('user_id', $id)->where('status', 1)->get();
        $event_cards_inactive = EventCard::where('user_id', $id)->where('status', 0)->get();

        $amount_recharge = CardTransaction::where('user_id', $id)->where('type_of_transaction_id', 0)->get();
        $amount_refund1 = CardTransaction::where('user_id', $id)->where('type_of_transaction_id', 2)->get();
        $amount_refund2 = Refund::where('user_id', $id)->where('status', 1)->get();

        $amount_refund_total = $amount_refund1->sum('total') + $amount_refund2->sum('refund');

        $array[] = [
            'sell_made' => $sells_made->count(),
            'sell_verified' => $sells_verified->count(),
            'amount_sell_made' => $sells_made->sum('total'),
            'amount_sell_verified' => $sells_verified->sum('total'),
            'amount_sell_dinheiro' => $sells_made_dinheiro->sum('total'),
            'amount_sell_cartao' => $sells_made_cartao->sum('total'),
            'amount_sell_mpesa' => $sells_made_mpesa->sum('total'),
            'amount_sell_emola' => $sells_made_emola->sum('total'),
            'amount_sell_cashless' => $sells_made_cashless->sum('total'),
            'amount_refund' => $amount_refund_total,
            'amount_recharge' => $amount_recharge->sum('total'),
            'cards_registered' => $event_cards_registered->count(),
            'cards_active' => $event_cards_active->count(),
            'cards_inactive' => $event_cards_inactive->count(),
        ];

        return response([
            'operation' => $array,
        ], 200);
    }
}
