<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paypay_payments', function (Blueprint $t) {
            $t->id();
            $t->string('out_trade_no', 32)->unique();     // our order number sent to PayPay
            $t->string('trade_no', 40)->nullable()->index(); // PayPay's order number
            $t->nullableMorphs('payable');                // your ticket order / purchase
            $t->string('method', 16);                     // app | qr | express | reference | agent
            $t->decimal('amount', 15, 2);
            $t->string('currency', 3)->default('AOA');
            $t->string('subject', 256);
            $t->string('status', 16)->default('pending')->index(); // pending | paid | closed | failed | review
            $t->string('phone', 16)->nullable();
            $t->string('payer_ip', 45)->nullable();
            $t->json('gateway_response')->nullable();
            $t->string('refund_out_trade_no', 32)->nullable()->unique();
            $t->string('refund_status', 16)->nullable();  // pending | success | failed
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypay_payments');
    }
};
