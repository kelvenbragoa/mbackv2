<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agora_lives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->string('channel')->unique();
            $table->string('status')->default('idle');
            $table->unsignedTinyInteger('max_guests')->default(1);
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->timestamp('host_seen_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::create('agora_live_guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agora_live_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('uid');
            $table->string('status')->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('agora_live_id')->references('id')->on('agora_lives')->cascadeOnDelete();
            $table->index(['agora_live_id', 'status']);
            $table->index(['agora_live_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agora_live_guests');
        Schema::dropIfExists('agora_lives');
    }
};
