<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Http\Traits\PaginatesRequests;
use App\Models\BarStore;
use App\Models\Products;
use App\Models\ProductStockMovement;
use App\Models\StockNote;
use App\Services\StockNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class PromotorStockNoteController extends Controller
{
    use AuthorizesEventAccess;
    use PaginatesRequests;

    public function index(Request $request)
    {
        $eventId = (int) $request->query('event_id');
        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }

        $barId = $request->filled('bar_store_id') ? (int) $request->query('bar_store_id') : null;

        $notes = StockNote::query()
            ->with(['barstore:id,name', 'toBarstore:id,name', 'creator:id,name'])
            ->withCount('items')
            ->where('event_id', $eventId)
            ->when($barId, function ($q) use ($barId) {
                $q->where(function ($inner) use ($barId) {
                    $inner->where('bar_store_id', $barId)
                        ->orWhere('to_bar_store_id', $barId);
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->orderByDesc('id')
            ->paginate($this->perPageTable($request))
            ->appends($request->query());

        return response()->json([
            'notes' => $notes,
        ]);
    }

    public function create(Request $request)
    {
        $eventId = (int) $request->query('event_id');
        $barId = (int) $request->query('bar_store_id');

        if ($denied = $this->denyEventAccess($eventId)) {
            return $denied;
        }

        $bar = BarStore::where('event_id', $eventId)->find($barId);
        if (! $bar) {
            return response()->json(['message' => 'Bar não encontrado.'], 404);
        }

        $products = Products::where('event_id', $eventId)
            ->where('bar_store_id', $bar->id)
            ->orderBy('name')
            ->get(['id', 'name', 'qtd', 'sell_price', 'buy_price', 'bar_store_id', 'event_id']);

        $bars = BarStore::where('event_id', $eventId)->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'bar' => $bar,
            'bars' => $bars,
            'products' => $products,
        ]);
    }

    public function store(Request $request, StockNoteService $service)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'integer'],
            'bar_store_id' => ['required', 'integer'],
            'to_bar_store_id' => ['nullable', 'integer', 'different:bar_store_id'],
            'type' => ['required', 'in:entry,exit,transfer,inventory'],
            'reference' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->input('type') === 'transfer' && ! $request->filled('to_bar_store_id')) {
                $validator->errors()->add('to_bar_store_id', 'Indica o bar de destino.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($denied = $this->denyEventAccess($data['event_id'])) {
            return $denied;
        }

        try {
            if ($data['type'] === StockNoteService::TYPE_TRANSFER) {
                $note = $service->createTransferConfirmed($data);
                $message = 'Transferência de stock registada.';
            } elseif ($data['type'] === StockNoteService::TYPE_INVENTORY) {
                $note = $service->createInventoryConfirmed($data);
                $message = 'Inventário registado.';
            } else {
                $note = $service->createConfirmed($data);
                $message = $data['type'] === 'entry' ? 'Nota de entrada registada.' : 'Nota de saída registada.';
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $message,
            'note' => $note,
        ], 201);
    }

    public function show(string $id)
    {
        $note = StockNote::with([
            'items.product:id,name,qtd,sell_price,buy_price',
            'items.toProduct:id,name,qtd,sell_price,buy_price',
            'barstore:id,name',
            'toBarstore:id,name',
            'creator:id,name',
            'confirmer:id,name',
        ])->find($id);

        if (! $note) {
            return response()->json(['message' => 'Nota não encontrada.'], 404);
        }

        if ($denied = $this->denyEventAccess($note->event_id)) {
            return $denied;
        }

        return response()->json([
            'note' => $note,
        ]);
    }

    public function productMovements(Request $request, string $productId)
    {
        $product = Products::find($productId);
        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        if ($denied = $this->denyEventAccess($product->event_id)) {
            return $denied;
        }

        $movements = ProductStockMovement::query()
            ->with(['user:id,name', 'note:id,type,reference,supplier,to_bar_store_id'])
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->paginate($this->perPageTable($request))
            ->appends($request->query());

        return response()->json([
            'product' => $product->load('barstore:id,name'),
            'movements' => $movements,
        ]);
    }
}
