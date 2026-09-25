<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agora_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agora_live_id')->constrained('agora_lives')->cascadeOnDelete();
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('unique_viewers')->default(0);
            $table->timestamps();

            $table->index(['agora_live_id', 'started_at']);
        });

        Schema::create('agora_live_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agora_live_session_id')->constrained('agora_live_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['agora_live_session_id', 'user_id']);
            $table->index(['agora_live_session_id', 'last_seen_at']);
        });

        Schema::table('agora_lives', function (Blueprint $table) {
            $table->unsignedBigInteger('current_session_id')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('agora_lives', function (Blueprint $table) {
            $table->dropColumn('current_session_id');
        });
        Schema::dropIfExists('agora_live_viewers');
        Schema::dropIfExists('agora_live_sessions');
    }
};
