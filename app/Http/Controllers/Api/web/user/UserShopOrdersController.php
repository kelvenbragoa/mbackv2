<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesRequests;
use App\Models\SellShop;
use App\Support\ShopOrderPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserShopOrdersController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Utilizador não autenticado'], 401);
        }

        $query = $this->ownedQuery($user->id)
            ->with(['details', 'event.province', 'event.city', 'transaction'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(function ($inner) use ($search) {
                    $inner->where('qrcode', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('event', function ($eventQuery) use ($search) {
                            $eventQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('transaction', function ($txQuery) use ($search) {
                            $txQuery->where('reference', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->query('status');
                if ($status === 'picked_up') {
                    $q->whereNotNull('picked_up_at');
                } elseif ($status === 'pending') {
                    $q->whereNull('picked_up_at');
                }
            })
            ->orderByDesc('id');

        $orders = (clone $query)->paginate($this->perPage($request))->appends($request->query());
        $orders->setCollection(
            $orders->getCollection()->map(fn (SellShop $sell) => $sell->toOrderArray())
        );

        $baseQuery = $this->ownedQuery($user->id);

        return response()->json([
            'orders' => $orders,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'pending' => (clone $baseQuery)->whereNull('picked_up_at')->count(),
                'picked_up' => (clone $baseQuery)->whereNotNull('picked_up_at')->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $sell = $this->findOwned((int) $id);
        if (! $sell) {
            return response()->json(['error' => 'Recibo não encontrado'], 404);
        }

        return response()->json(['order' => $sell->toOrderArray()]);
    }

    public function pdf(string $id)
    {
        $sell = $this->findOwned((int) $id);
        if (! $sell) {
            return response()->json(['error' => 'Recibo não encontrado'], 404);
        }

        $download = ShopOrderPdf::download($sell);
        if (! $download) {
            return response()->json(['message' => 'Não foi possível gerar o recibo.'], 422);
        }

        return $download;
    }

    private function ownedQuery(int $userId)
    {
        return SellShop::query()
            ->where('user_id', $userId)
            ->where('status', SellShop::STATUS_PAID);
    }

    private function findOwned(int $id): ?SellShop
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return $this->ownedQuery($user->id)
            ->with(['details', 'event.province', 'event.city', 'transaction'])
            ->where('id', $id)
            ->first();
    }
}
