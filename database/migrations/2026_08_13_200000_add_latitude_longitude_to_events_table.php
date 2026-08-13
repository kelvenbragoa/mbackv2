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
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        $this->seedBeiraCoordinates();
    }

    /**
     * Assign random coordinates around Beira to existing events in that city.
     */
    private function seedBeiraCoordinates(): void
    {
        $beiraCityIds = DB::table('cities')
            ->where('name', 'like', '%Beira%')
            ->pluck('id');

        if ($beiraCityIds->isEmpty()) {
            return;
        }

        $events = DB::table('events')
            ->whereIn('city_id', $beiraCityIds)
            ->where(function ($query) {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude')
                    ->orWhere('latitude', 0)
                    ->orWhere('longitude', 0);
            })
            ->get(['id']);

        // Approximate urban Beira bounds
        $latMin = -19.8600;
        $latMax = -19.7800;
        $lngMin = 34.8200;
        $lngMax = 34.9000;

        foreach ($events as $event) {
            $latitude = $latMin + (mt_rand() / mt_getrandmax()) * ($latMax - $latMin);
            $longitude = $lngMin + (mt_rand() / mt_getrandmax()) * ($lngMax - $lngMin);

            DB::table('events')
                ->where('id', $event->id)
                ->update([
                    'latitude' => round($latitude, 7),
                    'longitude' => round($longitude, 7),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
