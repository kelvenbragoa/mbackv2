<?php

namespace App\Http\Controllers\Api\mobile\protocols;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SellShop;
use Illuminate\Support\Facades\DB;

class ShopPickupController extends Controller
{
    public function index($id)
    {
        return response([
            'all_shops' => $this->paidQuery($id)->get()->map->toPickupArray()->values(),
        ], 200);
    }

    public function pending($id)
    {
        return response([
            'pending_shops' => $this->paidQuery($id)->whereNull('picked_up_at')->get()->map->toPickupArray()->values(),
        ], 200);
    }

    public function done($id)
    {
        return response([
            'done_shops' => $this->paidQuery($id)->whereNotNull('picked_up_at')->get()->map->toPickupArray()->values(),
        ], 200);
    }

    public function detail($id)
    {
        $sell = SellShop::with(['details', 'event', 'transaction'])->find($id);
        if (! $sell || ! $sell->isPaid()) {
            return response([
                'message' => 'Encomenda da loja não encontrada.',
            ], 404);
        }

        return response([
            'shop' => [$sell->toPickupArray()],
        ], 200);
    }

    public function status($id)
    {
        $sell = SellShop::findByAccessCode((string) $id);
        if (! $sell) {
            return response([
                'message' => 'Recibo da loja não encontrado.',
            ], 404);
        }

        if ((int) $sell->status === SellShop::STATUS_CANCELLED) {
            return response([
                'message' => 'Esta encomenda foi cancelada.',
            ], 422);
        }

        if (! $sell->isPaid()) {
            return response([
                'message' => 'Esta encomenda ainda não está paga.',
            ], 422);
        }

        return response([
            'id' => $sell->id,
            'status' => $sell->isPickedUp() ? 0 : 1,
            'event_id' => $sell->event_id,
            'qrcode' => $sell->qrcode,
            'name' => $sell->name,
        ], 200);
    }

    public function verify($id, $userid)
    {
        $sell = SellShop::findByAccessCode((string) $id);
        if (! $sell) {
            return response([
                'message' => 'Recibo da loja não encontrado.',
            ], 404);
        }

        return DB::transaction(function () use ($sell, $userid) {
            $locked = SellShop::where('id', $sell->id)->lockForUpdate()->first();
            if (! $locked) {
                return response([
                    'message' => 'Recibo da loja não encontrado.',
                ], 404);
            }

            if ((int) $locked->status === SellShop::STATUS_CANCELLED) {
                return response([
                    'message' => 'Esta encomenda foi cancelada.',
                ], 422);
            }

            if (! $locked->isPaid()) {
                return response([
                    'message' => 'Esta encomenda ainda não está paga.',
                ], 422);
            }

            if ($locked->isPickedUp()) {
                return response([
                    'message' => 'Esta encomenda já foi levantada.',
                ], 409);
            }

            $locked->update([
                'picked_up_at' => now(),
                'picked_up_by' => $userid,
            ]);

            return response([
                'message' => 'Produtos confirmados com sucesso',
            ], 200);
        });
    }

    private function paidQuery($eventId)
    {
        $event = Event::find($eventId);

        return SellShop::query()
            ->with(['details', 'event', 'transaction'])
            ->where('event_id', $event?->id ?? $eventId)
            ->where('status', SellShop::STATUS_PAID)
            ->orderByDesc('id');
    }
}
