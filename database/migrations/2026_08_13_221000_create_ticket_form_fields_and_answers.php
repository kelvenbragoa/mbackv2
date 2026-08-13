<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('label');
            $table->string('field_key');
            $table->string('type', 32); // text, textarea, number, select, checkbox, terms
            $table->json('options')->nullable();
            $table->text('terms_text')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('ticket_id');
            $table->unique(['ticket_id', 'field_key']);
        });

        Schema::table('sell_details', function (Blueprint $table) {
            $table->json('form_answers')->nullable()->after('protocol_id');
        });

        Schema::table('temporary_sell_details', function (Blueprint $table) {
            $table->json('form_answers')->nullable()->after('protocol_id');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_sell_details', function (Blueprint $table) {
            $table->dropColumn('form_answers');
        });

        Schema::table('sell_details', function (Blueprint $table) {
            $table->dropColumn('form_answers');
        });

        Schema::dropIfExists('ticket_form_fields');
    }
};
