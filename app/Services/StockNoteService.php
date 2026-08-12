<?php

namespace App\Services;

use App\Models\BarStore;
use App\Models\Products;
use App\Models\ProductStockMovement;
use App\Models\StockNote;
use App\Models\StockNoteItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockNoteService
{
    public const TYPE_ENTRY = 'entry';
    public const TYPE_EXIT = 'exit';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_INVENTORY = 'inventory';

    public const MOVE_TRANSFER_OUT = 'transfer_out';
    public const MOVE_TRANSFER_IN = 'transfer_in';
    public const MOVE_INVENTORY = 'inventory';

    /**
     * Create and immediately confirm a stock note with product lines.
     *
     * @param  array{event_id:int,bar_store_id:int,type:string,reference?:string,supplier?:string,note?:string,items:array<int,array{product_id:int,qty:int}>}  $payload
     */
    public function createConfirmed(array $payload): StockNote
    {
        $type = $payload['type'] ?? null;
        if (! in_array($type, [self::TYPE_ENTRY, self::TYPE_EXIT], true)) {
            throw new InvalidArgumentException('Tipo de nota inválido.');
        }

        $bar = BarStore::find($payload['bar_store_id'] ?? null);
        if (! $bar || (int) $bar->event_id !== (int) $payload['event_id']) {
            throw new InvalidArgumentException('Bar inválido para este evento.');
        }

        $items = collect($payload['items'] ?? [])
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'qty' => (int) ($item['qty'] ?? 0),
            ])
            ->filter(fn ($item) => $item['product_id'] > 0 && $item['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Indica pelo menos um produto com quantidade maior que zero.');
        }

        return DB::transaction(function () use ($payload, $type, $bar, $items) {
            $userId = Auth::id();

            $note = StockNote::create([
                'event_id' => $bar->event_id,
                'bar_store_id' => $bar->id,
                'type' => $type,
                'status' => 'confirmed',
                'reference' => $payload['reference'] ?? null,
                'supplier' => $payload['supplier'] ?? null,
                'note' => $payload['note'] ?? null,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($items as $line) {
                $product = Products::where('id', $line['product_id'])
                    ->where('event_id', $bar->event_id)
                    ->where('bar_store_id', $bar->id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new InvalidArgumentException("Produto #{$line['product_id']} não pertence a este bar.");
                }

                $before = (int) $product->qtd;
                $delta = $type === self::TYPE_ENTRY ? $line['qty'] : -$line['qty'];

                if ($type === self::TYPE_EXIT && $line['qty'] > $before) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente para \"{$product->name}\". Disponível: {$before}."
                    );
                }

                $after = $before + $delta;
                $product->qtd = $after;
                $product->save();

                $item = StockNoteItem::create([
                    'stock_note_id' => $note->id,
                    'product_id' => $product->id,
                    'qty' => $line['qty'],
                    'qty_before' => $before,
                    'qty_after' => $after,
                ]);

                ProductStockMovement::create([
                    'product_id' => $product->id,
                    'event_id' => $bar->event_id,
                    'bar_store_id' => $bar->id,
                    'type' => $type,
                    'qty_before' => $before,
                    'qty_delta' => $delta,
                    'qty_after' => $after,
                    'stock_note_id' => $note->id,
                    'stock_note_item_id' => $item->id,
                    'note' => $payload['note'] ?? null,
                    'user_id' => $userId,
                ]);
            }

            return $note->load(['items.product', 'barstore', 'creator']);
        });
    }

    /**
     * Transfer stock from one bar to another within the same event.
     * Destination products are matched by name (case-insensitive); created if missing.
     *
     * @param  array{event_id:int,bar_store_id:int,to_bar_store_id:int,reference?:string,note?:string,items:array<int,array{product_id:int,qty:int}>}  $payload
     */
    public function createTransferConfirmed(array $payload): StockNote
    {
        $fromBar = BarStore::find($payload['bar_store_id'] ?? null);
        $toBar = BarStore::find($payload['to_bar_store_id'] ?? null);
        $eventId = (int) ($payload['event_id'] ?? 0);

        if (! $fromBar || (int) $fromBar->event_id !== $eventId) {
            throw new InvalidArgumentException('Bar de origem inválido para este evento.');
        }

        if (! $toBar || (int) $toBar->event_id !== $eventId) {
            throw new InvalidArgumentException('Bar de destino inválido para este evento.');
        }

        if ((int) $fromBar->id === (int) $toBar->id) {
            throw new InvalidArgumentException('O bar de destino tem de ser diferente do de origem.');
        }

        $items = collect($payload['items'] ?? [])
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'qty' => (int) ($item['qty'] ?? 0),
            ])
            ->filter(fn ($item) => $item['product_id'] > 0 && $item['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Indica pelo menos um produto com quantidade maior que zero.');
        }

        return DB::transaction(function () use ($payload, $fromBar, $toBar, $items) {
            $userId = Auth::id();

            $note = StockNote::create([
                'event_id' => $fromBar->event_id,
                'bar_store_id' => $fromBar->id,
                'to_bar_store_id' => $toBar->id,
                'type' => self::TYPE_TRANSFER,
                'status' => 'confirmed',
                'reference' => $payload['reference'] ?? null,
                'supplier' => null,
                'note' => $payload['note'] ?? null,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($items as $line) {
                $fromProduct = Products::where('id', $line['product_id'])
                    ->where('event_id', $fromBar->event_id)
                    ->where('bar_store_id', $fromBar->id)
                    ->lockForUpdate()
                    ->first();

                if (! $fromProduct) {
                    throw new InvalidArgumentException("Produto #{$line['product_id']} não pertence ao bar de origem.");
                }

                $fromBefore = (int) $fromProduct->qtd;
                if ($line['qty'] > $fromBefore) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente para \"{$fromProduct->name}\". Disponível: {$fromBefore}."
                    );
                }

                $toProduct = $this->resolveDestinationProduct($fromProduct, $toBar);
                $toProduct = Products::where('id', $toProduct->id)->lockForUpdate()->first();

                $toBefore = (int) $toProduct->qtd;
                $fromAfter = $fromBefore - $line['qty'];
                $toAfter = $toBefore + $line['qty'];

                $fromProduct->qtd = $fromAfter;
                $fromProduct->save();

                $toProduct->qtd = $toAfter;
                $toProduct->save();

                $item = StockNoteItem::create([
                    'stock_note_id' => $note->id,
                    'product_id' => $fromProduct->id,
                    'to_product_id' => $toProduct->id,
                    'qty' => $line['qty'],
                    'qty_before' => $fromBefore,
                    'qty_after' => $fromAfter,
                    'to_qty_before' => $toBefore,
                    'to_qty_after' => $toAfter,
                ]);

                $transferNote = sprintf(
                    'Transferência %s → %s%s',
                    $fromBar->name,
                    $toBar->name,
                    ! empty($payload['note']) ? ' | '.$payload['note'] : ''
                );

                ProductStockMovement::create([
                    'product_id' => $fromProduct->id,
                    'event_id' => $fromBar->event_id,
                    'bar_store_id' => $fromBar->id,
                    'type' => self::MOVE_TRANSFER_OUT,
                    'qty_before' => $fromBefore,
                    'qty_delta' => -$line['qty'],
                    'qty_after' => $fromAfter,
                    'stock_note_id' => $note->id,
                    'stock_note_item_id' => $item->id,
                    'reference_type' => 'product',
                    'reference_id' => $toProduct->id,
                    'note' => $transferNote,
                    'user_id' => $userId,
                ]);

                ProductStockMovement::create([
                    'product_id' => $toProduct->id,
                    'event_id' => $toBar->event_id,
                    'bar_store_id' => $toBar->id,
                    'type' => self::MOVE_TRANSFER_IN,
                    'qty_before' => $toBefore,
                    'qty_delta' => $line['qty'],
                    'qty_after' => $toAfter,
                    'stock_note_id' => $note->id,
                    'stock_note_item_id' => $item->id,
                    'reference_type' => 'product',
                    'reference_id' => $fromProduct->id,
                    'note' => $transferNote,
                    'user_id' => $userId,
                ]);
            }

            return $note->load([
                'items.product',
                'items.toProduct',
                'barstore',
                'toBarstore',
                'creator',
            ]);
        });
    }

    /**
     * Inventory count: set product stock to counted qty and record adjustments.
     * Item qty = counted physical quantity (may be 0).
     *
     * @param  array{event_id:int,bar_store_id:int,reference?:string,note?:string,items:array<int,array{product_id:int,qty:int}>}  $payload
     */
    public function createInventoryConfirmed(array $payload): StockNote
    {
        $bar = BarStore::find($payload['bar_store_id'] ?? null);
        if (! $bar || (int) $bar->event_id !== (int) $payload['event_id']) {
            throw new InvalidArgumentException('Bar inválido para este evento.');
        }

        $items = collect($payload['items'] ?? [])
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'qty' => (int) ($item['qty'] ?? 0),
            ])
            ->filter(fn ($item) => $item['product_id'] > 0 && $item['qty'] >= 0)
            ->unique('product_id')
            ->values();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Indica pelo menos um produto para inventariar.');
        }

        return DB::transaction(function () use ($payload, $bar, $items) {
            $userId = Auth::id();

            $note = StockNote::create([
                'event_id' => $bar->event_id,
                'bar_store_id' => $bar->id,
                'type' => self::TYPE_INVENTORY,
                'status' => 'confirmed',
                'reference' => $payload['reference'] ?? null,
                'supplier' => null,
                'note' => $payload['note'] ?? null,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($items as $line) {
                $product = Products::where('id', $line['product_id'])
                    ->where('event_id', $bar->event_id)
                    ->where('bar_store_id', $bar->id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new InvalidArgumentException("Produto #{$line['product_id']} não pertence a este bar.");
                }

                $before = (int) $product->qtd;
                $after = $line['qty'];
                $delta = $after - $before;

                $product->qtd = $after;
                $product->save();

                $item = StockNoteItem::create([
                    'stock_note_id' => $note->id,
                    'product_id' => $product->id,
                    'qty' => $after,
                    'qty_before' => $before,
                    'qty_after' => $after,
                ]);

                if ($delta !== 0) {
                    ProductStockMovement::create([
                        'product_id' => $product->id,
                        'event_id' => $bar->event_id,
                        'bar_store_id' => $bar->id,
                        'type' => self::MOVE_INVENTORY,
                        'qty_before' => $before,
                        'qty_delta' => $delta,
                        'qty_after' => $after,
                        'stock_note_id' => $note->id,
                        'stock_note_item_id' => $item->id,
                        'note' => $payload['note'] ?? 'Ajuste de inventário',
                        'user_id' => $userId,
                    ]);
                }
            }

            return $note->load(['items.product', 'barstore', 'creator']);
        });
    }

    protected function resolveDestinationProduct(Products $fromProduct, BarStore $toBar): Products
    {
        $existing = Products::where('event_id', $toBar->event_id)
            ->where('bar_store_id', $toBar->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $fromProduct->name))])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Products::create([
            'name' => $fromProduct->name,
            'buy_price' => $fromProduct->buy_price,
            'sell_price' => $fromProduct->sell_price,
            'qtd' => 0,
            'event_id' => $toBar->event_id,
            'bar_store_id' => $toBar->id,
        ]);
    }
}
