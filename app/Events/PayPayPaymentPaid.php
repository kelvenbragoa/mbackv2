<?php

namespace App\Events;

use App\Models\PayPayPayment;

/**
 * Fired exactly once per payment, when PayPay confirms TRADE_FINISHED (funds settled).
 * Attach your ticket-issuing listener to this. Make it queued and idempotent.
 */
class PayPayPaymentPaid
{
    public function __construct(public readonly PayPayPayment $payment)
    {
    }
}
