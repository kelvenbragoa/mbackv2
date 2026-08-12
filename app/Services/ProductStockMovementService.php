<?php

namespace App\Services;

use App\Models\ProductStockMovement;
use App\Models\Products;
use InvalidArgumentException;

class ProductStockMovementService
{
    public const TYPE_SALE = 'sale';
    public const TYPE_SALE_CANCEL = 'sale_cancel';

    /**
     * Deduct stock for a bar sale and write a movement row.
     */
    public function applySale(
        Products $product,
        int $qty,
        int $eventId,
        ?int $barStoreId,
        int $sellId,
        ?int $sellDetailId = null,
        ?string $note = null
    ): ProductStockMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantidade de venda inválida.');
        }

        $locked = Products::where('id', $product->id)->lockForUpdate()->first();
        if (! $locked) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $before = (int) $locked->qtd;
        if ($qty > $before) {
            throw new InvalidArgumentException(
                "Stock insuficiente para \"{$locked->name}\". Disponível: {$before}."
            );
        }

        $after = $before - $qty;
        $locked->qtd = $after;
        $locked->save();

        return ProductStockMovement::create([
            'product_id' => $locked->id,
            'event_id' => $eventId,
            'bar_store_id' => $barStoreId,
            'type' => self::TYPE_SALE,
            'qty_before' => $before,
            'qty_delta' => -$qty,
            'qty_after' => $after,
            'reference_type' => 'sell_bar',
            'reference_id' => $sellId,
            'note' => $note ?? "Venda #{$sellId}".($sellDetailId ? " (linha #{$sellDetailId})" : ''),
            'user_id' => null,
        ]);
    }

    /**
     * Restore stock when a bar sale is cancelled/deleted.
     */
    public function applySaleCancel(
        Products $product,
        int $qty,
        int $eventId,
        ?int $barStoreId,
        int $sellId,
        ?string $note = null
    ): ProductStockMovement {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantidade de cancelamento inválida.');
        }

        $locked = Products::where('id', $product->id)->lockForUpdate()->first();
        if (! $locked) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $before = (int) $locked->qtd;
        $after = $before + $qty;
        $locked->qtd = $after;
        $locked->save();

        return ProductStockMovement::create([
            'product_id' => $locked->id,
            'event_id' => $eventId,
            'bar_store_id' => $barStoreId,
            'type' => self::TYPE_SALE_CANCEL,
            'qty_before' => $before,
            'qty_delta' => $qty,
            'qty_after' => $after,
            'reference_type' => 'sell_bar',
            'reference_id' => $sellId,
            'note' => $note ?? "Cancelamento venda #{$sellId}",
            'user_id' => null,
        ]);
    }
}
