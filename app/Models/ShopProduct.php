<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    use HasFactory;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;

    protected $guarded = [];

    protected $casts = [
        'sell_price' => 'float',
        'qtd' => 'integer',
        'status' => 'integer',
        'pickup_only' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function variants()
    {
        return $this->hasMany(ShopProductVariant::class)->orderBy('id');
    }

    public function sellDetails()
    {
        return $this->hasMany(SellDetailShop::class, 'shop_product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function stock(): int
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (int) $this->variants->sum('qtd');
        }

        return (int) $this->qtd;
    }

    public function toPublicArray(): array
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'sell_price' => (float) $this->sell_price,
            'qtd' => $this->stock(),
            'pickup_only' => (bool) $this->pickup_only,
            'sold_out' => $this->stock() <= 0,
            'variants' => $variants->map(function (ShopProductVariant $variant) {
                return $variant->toPublicArray((float) $this->sell_price);
            })->values()->all(),
        ];
    }
}
