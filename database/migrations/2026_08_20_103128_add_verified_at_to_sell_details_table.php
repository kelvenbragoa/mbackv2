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
        Schema::table('sell_details', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_by')->nullable()->after('protocol_id');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        DB::table('sell_details')
            ->where('status', 0)
            ->whereNull('verified_at')
            ->update(['verified_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sell_details', function (Blueprint $table) {
            $table->dropColumn(['verified_by', 'verified_at']);
        });
    }
};
