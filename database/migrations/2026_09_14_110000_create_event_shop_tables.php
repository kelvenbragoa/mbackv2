<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('sell_price', 10, 2);
            $table->unsignedInteger('qtd')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('pickup_only')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_product_id')->index();
            $table->string('name');
            $table->unsignedInteger('qtd')->default(0);
            $table->decimal('sell_price', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('sell_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->decimal('total', 10, 2);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('method')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('qrcode', 16)->nullable()->unique();
            $table->timestamp('picked_up_at')->nullable();
            $table->unsignedBigInteger('picked_up_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sell_detail_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sell_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('shop_product_id')->index();
            $table->unsignedBigInteger('shop_product_variant_id')->nullable()->index();
            $table->unsignedInteger('qtd');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('product_name')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sell_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('reference');
            $table->string('method');
            $table->timestamps();
        });

        Schema::create('temporary_sell_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->decimal('total', 10, 2);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('method')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->timestamps();
        });

        Schema::create('temporary_sell_detail_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sell_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('shop_product_id')->index();
            $table->unsignedBigInteger('shop_product_variant_id')->nullable()->index();
            $table->unsignedInteger('qtd');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('product_name')->nullable();
            $table->timestamps();
        });

        Schema::create('temporary_shop_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sell_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('reference');
            $table->string('method');
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_shop_transactions');
        Schema::dropIfExists('temporary_sell_detail_shops');
        Schema::dropIfExists('temporary_sell_shops');
        Schema::dropIfExists('shop_transactions');
        Schema::dropIfExists('sell_detail_shops');
        Schema::dropIfExists('sell_shops');
        Schema::dropIfExists('shop_product_variants');
        Schema::dropIfExists('shop_products');
    }
};
