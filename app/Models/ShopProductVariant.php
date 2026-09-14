<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProductVariant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'qtd' => 'integer',
        'sell_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function sellDetails()
    {
        return $this->hasMany(SellDetailShop::class, 'shop_product_variant_id');
    }

    public function price(?float $fallback = null): float
    {
        if ($this->sell_price !== null) {
            return (float) $this->sell_price;
        }

        return (float) ($fallback ?? $this->product?->sell_price ?? 0);
    }

    public function toPublicArray(?float $productPrice = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'qtd' => (int) $this->qtd,
            'sell_price' => $this->price($productPrice),
            'sold_out' => (int) $this->qtd <= 0,
        ];
    }
}
