<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellDetailShop extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;
    public const STATUS_PAID = 1;
    public const STATUS_CANCELLED = 2;

    protected $guarded = [];

    protected $casts = [
        'qtd' => 'integer',
        'price' => 'float',
        'total' => 'float',
        'status' => 'integer',
    ];

    public function sell()
    {
        return $this->belongsTo(SellShop::class, 'sell_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ShopProductVariant::class, 'shop_product_variant_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
