<?php

namespace App\Models;

use App\Support\AccessCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellShop extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;
    public const STATUS_PAID = 1;
    public const STATUS_CANCELLED = 2;

    protected $guarded = [];

    protected $casts = [
        'total' => 'float',
        'status' => 'integer',
        'picked_up_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SellShop $sell) {
            if (empty($sell->qrcode)) {
                $sell->qrcode = AccessCode::uniqueShopQrcode();
            }
        });
    }

    public function details()
    {
        return $this->hasMany(SellDetailShop::class, 'sell_id');
    }

    public function transaction()
    {
        return $this->hasOne(ShopTransaction::class, 'sell_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return (int) $this->status === self::STATUS_PAID;
    }

    public function isPickedUp(): bool
    {
        return $this->picked_up_at !== null;
    }

    public static function findByAccessCode(string $code): ?self
    {
        $code = AccessCode::normalize($code);

        if ($code === '') {
            return null;
        }

        return static::query()
            ->where(function ($query) use ($code) {
                $query->where('qrcode', $code);

                if (ctype_digit($code) && strlen($code) < 10) {
                    $query->orWhere('id', (int) $code);
                }
            })
            ->first();
    }

    public function toPickupArray(): array
    {
        $this->loadMissing(['details', 'event', 'transaction']);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'qrcode' => $this->qrcode,
            'total' => (float) $this->total,
            'status' => $this->isPickedUp() ? 0 : 1,
            'order_status' => (int) $this->status,
            'picked_up_at' => $this->picked_up_at,
            'picked_up_by' => $this->picked_up_by,
            'created_at' => $this->created_at,
            'event' => $this->event,
            'transaction' => $this->transaction,
            'details' => $this->details->map(function (SellDetailShop $line) {
                return [
                    'id' => $line->id,
                    'product_name' => $line->product_name,
                    'qtd' => (int) $line->qtd,
                    'price' => (float) $line->price,
                    'total' => (float) $line->total,
                ];
            })->values()->all(),
        ];
    }

    public function toOrderArray(): array
    {
        $this->loadMissing(['details', 'event.province', 'event.city', 'transaction']);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'total' => (float) $this->total,
            'status' => (int) $this->status,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'qrcode' => $this->qrcode,
            'picked_up_at' => $this->picked_up_at,
            'created_at' => $this->created_at,
            'event' => $this->event,
            'transaction' => $this->transaction,
            'details' => $this->details->map(function (SellDetailShop $line) {
                return [
                    'id' => $line->id,
                    'shop_product_id' => $line->shop_product_id,
                    'shop_product_variant_id' => $line->shop_product_variant_id,
                    'product_name' => $line->product_name,
                    'qtd' => (int) $line->qtd,
                    'price' => (float) $line->price,
                    'total' => (float) $line->total,
                ];
            })->values()->all(),
        ];
    }
}
