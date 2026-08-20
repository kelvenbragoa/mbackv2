<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('distribution', 20); // internal | store
            $table->string('package_name')->nullable();
            $table->string('play_store_url')->nullable();
            $table->string('app_store_url')->nullable();
            $table->unsignedInteger('min_version_code')->default(0);
            $table->boolean('force_update')->default(false);
            $table->timestamps();
        });

        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('platform', 20)->default('android');
            $table->string('version_name');
            $table->unsignedInteger('version_code');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('changelog')->nullable();
            $table->boolean('force_update')->default(false);
            $table->timestamps();

            $table->unique(['app_id', 'platform', 'version_code']);
            $table->index(['app_id', 'platform', 'version_code']);
        });

        $now = now();

        DB::table('apps')->insert([
            [
                'slug' => 'mticket-bar',
                'name' => 'Mticket Bar',
                'description' => 'App da equipa de bar: vendas, cashless e verificação de senhas.',
                'distribution' => 'internal',
                'package_name' => 'mz.co.mticket.mticketbar',
                'play_store_url' => null,
                'app_store_url' => null,
                'min_version_code' => 0,
                'force_update' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'mticket-checkin',
                'name' => 'Mticket Check-in',
                'description' => 'App da portaria: validação de bilhetes e convites.',
                'distribution' => 'internal',
                'package_name' => 'mz.co.mticket.mticketcheckin',
                'play_store_url' => null,
                'app_store_url' => null,
                'min_version_code' => 0,
                'force_update' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'mticket-client',
                'name' => 'Mticket Client',
                'description' => 'App do público. Actualizações pela Play Store e App Store.',
                'distribution' => 'store',
                'package_name' => 'mz.co.mticket.client',
                'play_store_url' => 'https://play.google.com/store/apps/details?id=mz.co.mticket.client',
                'app_store_url' => 'https://apps.apple.com/mz/app/mticket/id6801146792',
                'min_version_code' => 0,
                'force_update' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('apps');
    }
};
