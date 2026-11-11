<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayPayOrderItem extends Model
{
    protected $table = 'paypay_order_items';

    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
        'total' => 'float',
        'form_answers' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PayPayOrder::class, 'paypay_order_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
