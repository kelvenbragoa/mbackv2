<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sell_detail_bars', function (Blueprint $table) {
            $table->id();
            $table->integer('sell_id');
            $table->integer('user_id');
            $table->integer('event_id');
            $table->integer('product_id');
            $table->integer('status');
            $table->integer('qtd');
            $table->float('price',8,2);
            $table->float('total',8,2);
            $table->integer('bar_store_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sell_detail_bars');
    }
};
