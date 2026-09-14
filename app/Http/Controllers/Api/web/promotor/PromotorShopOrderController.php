<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\SellDetailShop;
use App\Models\SellShop;
use App\Services\ShopStockService;
use Illuminate\Support\Facades\DB;

class PromotorShopOrderController extends Controller
{
    use AuthorizesEventAccess;

    public function cancel($id)
    {
        $sell = SellShop::find($id);
        if (! $sell) {
            return response()->json(['message' => 'Encomenda da loja não encontrada.'], 404);
        }

        if ($denied = $this->denyEventAccess($sell->event_id)) {
            return $denied;
        }

        return DB::transaction(function () use ($sell) {
            $locked = SellShop::where('id', $sell->id)->lockForUpdate()->first();
            if (! $locked) {
                return response()->json(['message' => 'Encomenda da loja não encontrada.'], 404);
            }

            if ((int) $locked->status === SellShop::STATUS_CANCELLED) {
                return response()->json(['message' => 'Esta encomenda já está cancelada.'], 422);
            }

            if (! $locked->isPaid()) {
                return response()->json(['message' => 'Só é possível cancelar encomendas pagas.'], 422);
            }

            if ($locked->isPickedUp()) {
                return response()->json(['message' => 'Não é possível cancelar uma encomenda já levantada.'], 422);
            }

            $locked->load('details');
            app(ShopStockService::class)->restoreOrder($locked);

            $locked->update(['status' => SellShop::STATUS_CANCELLED]);
            $locked->details()->update(['status' => SellDetailShop::STATUS_CANCELLED]);

            return response()->json([
                'message' => 'Encomenda cancelada. O stock foi reposto.',
            ]);
        });
    }
}
