<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\Ticket;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SellController extends Controller
{
    public function index($userid)
    {
        return response([
            'sells' => Sell::with('ticket:id,name')->with('user')->where('protocol_id', $userid)->orderBy('id', 'desc')->get(),
        ], 200);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if (($data['total'] ?? 0) == 0) {
            return response([
                'message' => 'Nenhuma venda efetuada.',
            ], 403);
        }

        $protocolId = (int) ($data['protocol_id'] ?? 0);
        if ($protocolId <= 0) {
            return response([
                'message' => 'Protocolo inválido.',
            ], 403);
        }

        $method = (string) ($data['method'] ?? '');
        $lockName = 'protocol-sell-'.$protocolId;
        $gotLock = collect(DB::select('SELECT GET_LOCK(?, 10) AS l', [$lockName]))->first()->l ?? 0;

        if ((int) $gotLock !== 1) {
            return response([
                'message' => 'Venda já em processamento. Aguarde e verifique Minhas Vendas.',
                'code' => 'sell_in_progress',
            ], 409);
        }

        try {
            $since = now()->subSeconds(15);
            $recentSellIds = Transaction::where('protocol_id', $protocolId)
                ->where('method', $method)
                ->where('created_at', '>=', $since)
                ->pluck('sell_id');

            if ($recentSellIds->isNotEmpty()) {
                $recentTotal = (float) Sell::whereIn('id', $recentSellIds)->sum('total');
                if ($recentTotal === (float) $data['total']) {
                    return response([
                        'message' => 'Venda duplicada bloqueada. Já existe uma venda idêntica há poucos segundos. Verifique Minhas Vendas.',
                        'code' => 'duplicate_sell',
                    ], 409);
                }
            }

            $pendingCart = Carts::where('protocol_id', $protocolId)->whereNull('sell_id')->get();
            if ($pendingCart->isEmpty()) {
                return response([
                    'message' => 'Carrinho vazio. A venda pode já ter sido processada.',
                    'code' => 'empty_cart',
                ], 409);
            }

            try {
                DB::transaction(function () use ($data, $protocolId, $method) {
                    $cart = Carts::with('ticket')
                        ->where('protocol_id', $protocolId)
                        ->whereNull('sell_id')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($cart->isEmpty()) {
                        throw new InvalidArgumentException('Carrinho vazio ou venda já processada.');
                    }

                    $ref = $protocolId.'-'.$method;

                    foreach ($cart as $item) {
                        $ticket = Ticket::where('id', $item->ticket_id)->lockForUpdate()->first();
                        if (! $ticket) {
                            throw new InvalidArgumentException('Bilhete #'.$item->ticket_id.' não encontrado.');
                        }

                        if ((int) $item->qtd > (int) $ticket->max_qtd) {
                            throw new InvalidArgumentException(
                                'Stock insuficiente para "'.$ticket->name.'". Disponível: '.$ticket->max_qtd.'.'
                            );
                        }

                        $price = $item->ticket->price ?? $ticket->price;
                        $lineTotal = $price * $item->qtd;

                        $sell = Sell::create([
                            'user_id' => 0,
                            'protocol_id' => $protocolId,
                            'event_id' => $item->event_id,
                            'ticket_id' => $item->ticket_id,
                            'qty' => $item->qtd,
                            'price' => $price,
                            'total' => $lineTotal,
                            'status' => 1,
                            'name' => 'Mticket',
                            'email' => 'suporte@mticket.co.mz',
                            'mobile' => '842648618',
                        ]);

                        Transaction::create([
                            'sell_id' => $sell->id,
                            'user_id' => 0,
                            'protocol_id' => $protocolId,
                            'reference' => $ref,
                            'method' => $method,
                        ]);

                        for ($i = 0; $i < $item->qtd; $i++) {
                            SellDetails::create([
                                'sell_id' => $sell->id,
                                'user_id' => 0,
                                'protocol_id' => $protocolId,
                                'event_id' => $item->event_id,
                                'ticket_id' => $item->ticket_id,
                                'status' => 1,
                                'name' => 'Mticket',
                                'email' => 'suporte@mticket.co.mz',
                                'mobile' => '842648618',
                            ]);
                        }

                        $ticket->update([
                            'max_qtd' => (int) $ticket->max_qtd - (int) $item->qtd,
                        ]);

                        $item->delete();
                    }
                });
            } catch (InvalidArgumentException $e) {
                return response([
                    'message' => $e->getMessage(),
                    'code' => 'sell_aborted',
                ], 409);
            }

            return response([
                'message' => 'Sua ordem foi efectuada com sucesso. Va até a  Minhas Vendas para visualizar.',
            ], 200);
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function selldetails($id)
    {
        return response([
            'selldetail' => SellDetails::where('sell_id', $id)->with('ticket:id,name')->with('sell.transaction')->with('user')->with('sell')->get(),
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

    public function destroy(string $id)
    {
        //
    }
}
