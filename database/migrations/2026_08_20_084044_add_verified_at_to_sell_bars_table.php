<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sell_bars', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->softDeletes();
        });

        Schema::table('sell_detail_bars', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('sell_bars')
            ->where('status', 0)
            ->whereNotNull('verified_by')
            ->whereNull('verified_at')
            ->update(['verified_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sell_detail_bars', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sell_bars', function (Blueprint $table) {
            $table->dropColumn('verified_at');
            $table->dropSoftDeletes();
        });
    }
};
