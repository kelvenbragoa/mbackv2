<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mux_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_id')->constrained('lives')->cascadeOnDelete();
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('unique_viewers')->default(0);
            $table->timestamps();

            $table->index(['live_id', 'started_at']);
        });

        Schema::create('mux_live_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mux_live_session_id')->constrained('mux_live_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['mux_live_session_id', 'user_id']);
            $table->index(['mux_live_session_id', 'last_seen_at']);
        });

        Schema::table('lives', function (Blueprint $table) {
            $table->unsignedBigInteger('current_session_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lives', function (Blueprint $table) {
            $table->dropColumn('current_session_id');
        });
        Schema::dropIfExists('mux_live_viewers');
        Schema::dropIfExists('mux_live_sessions');
    }
};
