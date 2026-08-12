<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('to_bar_store_id')->nullable()->after('bar_store_id');
            $table->index('to_bar_store_id');
        });

        Schema::table('stock_note_items', function (Blueprint $table) {
            $table->unsignedBigInteger('to_product_id')->nullable()->after('product_id');
            $table->integer('to_qty_before')->nullable()->after('qty_after');
            $table->integer('to_qty_after')->nullable()->after('to_qty_before');
            $table->index('to_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_note_items', function (Blueprint $table) {
            $table->dropIndex(['to_product_id']);
            $table->dropColumn(['to_product_id', 'to_qty_before', 'to_qty_after']);
        });

        Schema::table('stock_notes', function (Blueprint $table) {
            $table->dropIndex(['to_bar_store_id']);
            $table->dropColumn('to_bar_store_id');
        });
    }
};
