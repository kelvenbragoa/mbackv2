<?php

namespace App\Services;

use App\Models\SellShop;
use App\Models\ShopProduct;
use App\Models\ShopProductVariant;

class ShopStockService
{
    public function restoreOrder(SellShop $sell): void
    {
        $sell->loadMissing('details');

        foreach ($sell->details as $line) {
            $qty = (int) $line->qtd;
            if ($qty <= 0) {
                continue;
            }

            $product = ShopProduct::where('id', $line->shop_product_id)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }

            $variant = $line->shop_product_variant_id
                ? ShopProductVariant::where('id', $line->shop_product_variant_id)->lockForUpdate()->first()
                : null;

            $this->changeStock($product, $variant, $qty, 1);
        }
    }

    public function changeStock(ShopProduct $product, ?ShopProductVariant $variant, int $qty, int $direction): void
    {
        if ($variant) {
            $next = (int) $variant->qtd + ($direction * $qty);
            if ($next < 0) {
                throw new \RuntimeException('Stock insuficiente para '.$product->name.' / '.$variant->name.'.');
            }
            $variant->qtd = $next;
            $variant->save();
            $product->qtd = (int) $product->variants()->sum('qtd');
            $product->save();

            return;
        }

        $next = (int) $product->qtd + ($direction * $qty);
        if ($next < 0) {
            throw new \RuntimeException('Stock insuficiente para '.$product->name.'.');
        }
        $product->qtd = $next;
        $product->save();
    }
}
