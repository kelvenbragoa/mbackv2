<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('valid_for_days')->nullable()->after('is_live');
        });

        Schema::table('sell_details', function (Blueprint $table) {
            $table->timestamp('access_ends_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('valid_for_days');
        });

        Schema::table('sell_details', function (Blueprint $table) {
            $table->dropColumn('access_ends_at');
        });
    }
};
