<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporarySellDetailShop extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'qtd' => 'integer',
        'price' => 'float',
        'total' => 'float',
        'status' => 'integer',
    ];

    public function sell()
    {
        return $this->belongsTo(TemporarySellShop::class, 'sell_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ShopProductVariant::class, 'shop_product_variant_id');
    }
}
