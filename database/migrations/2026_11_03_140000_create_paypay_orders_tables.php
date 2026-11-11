<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypay_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('event_id')->index();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_mobile', 30)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('AOA');
            $table->string('pay_method', 16); // app | qr | express | reference | agent
            $table->string('status', 16)->default('pending')->index(); // pending | paid | fulfilled | closed | failed | review
            $table->string('phone', 16)->nullable();
            $table->string('payer_ip', 45)->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('paypay_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paypay_order_id')->constrained('paypay_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedInteger('qty');
            $table->decimal('price', 15, 2); // unit price AOA
            $table->decimal('total', 15, 2);
            $table->json('form_answers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypay_order_items');
        Schema::dropIfExists('paypay_orders');
    }
};
