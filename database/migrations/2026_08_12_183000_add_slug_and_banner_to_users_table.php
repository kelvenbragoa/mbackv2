<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('company_location');
            $table->string('banner')->nullable()->after('slug');
        });

        User::query()
            ->where('is_promotor', 1)
            ->where(function ($query) {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->each(function (User $user) {
                $base = Str::slug($user->company_name ?: $user->name) ?: 'promotor-'.$user->id;
                $slug = $base;
                $counter = 1;

                while (
                    User::where('slug', $slug)
                        ->where('id', '!=', $user->id)
                        ->exists()
                ) {
                    $slug = $base.'-'.$counter;
                    $counter++;
                }

                $user->forceFill(['slug' => $slug])->saveQuietly();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'banner']);
        });
    }
};
