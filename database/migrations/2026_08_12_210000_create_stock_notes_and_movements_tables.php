<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('bar_store_id');
            $table->string('type', 20); // entry | exit
            $table->string('status', 20)->default('confirmed'); // draft | confirmed
            $table->string('reference')->nullable();
            $table->string('supplier')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'bar_store_id']);
            $table->index(['type', 'status']);
        });

        Schema::create('stock_note_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_note_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->integer('qty_before')->nullable();
            $table->integer('qty_after')->nullable();
            $table->timestamps();

            $table->index('stock_note_id');
            $table->index('product_id');
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('bar_store_id')->nullable();
            $table->string('type', 30); // entry | exit | sale | sale_cancel | transfer | inventory | initial
            $table->integer('qty_before');
            $table->integer('qty_delta');
            $table->integer('qty_after');
            $table->unsignedBigInteger('stock_note_id')->nullable();
            $table->unsignedBigInteger('stock_note_item_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['event_id', 'bar_store_id']);
            $table->index('stock_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('stock_note_items');
        Schema::dropIfExists('stock_notes');
    }
};
