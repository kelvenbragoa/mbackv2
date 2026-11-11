<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayPayPayment extends Model
{
    protected $table = 'paypay_payments';

    protected $fillable = [
        'out_trade_no', 'trade_no', 'payable_type', 'payable_id', 'method', 'amount', 'currency',
        'subject', 'status', 'phone', 'payer_ip', 'gateway_response', 'refund_out_trade_no',
        'refund_status', 'paid_at', 'expires_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
