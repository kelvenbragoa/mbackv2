<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PayPayOrder extends Model
{
    protected $table = 'paypay_orders';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'fulfilled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayPayOrderItem::class, 'paypay_order_id');
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(PayPayPayment::class, 'payable');
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }
}
