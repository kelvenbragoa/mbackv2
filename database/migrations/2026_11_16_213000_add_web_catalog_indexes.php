<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('events', ['status_id', 'end_date'], 'events_status_id_end_date_index');
        $this->addIndex('events', ['user_id', 'status_id', 'end_date'], 'events_user_status_end_date_index');
        $this->addIndex('events', ['main_category_id', 'status_id'], 'events_category_status_index');
        $this->addIndex('like_events', 'event_id', 'like_events_event_id_index');
        $this->addIndex('sell_details', 'user_id', 'sell_details_user_id_index');
    }

    public function down(): void
    {
        $this->dropIndex('events', 'events_status_id_end_date_index');
        $this->dropIndex('events', 'events_user_status_end_date_index');
        $this->dropIndex('events', 'events_category_status_index');
        $this->dropIndex('like_events', 'like_events_event_id_index');
        $this->dropIndex('sell_details', 'sell_details_user_id_index');
    }

    private function addIndex(string $table, array|string $columns, string $name): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
