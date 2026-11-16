<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('events', ['status_id', 'id'], 'events_status_id_id_index');
        $this->addIndex('events', ['status_id', 'start_date'], 'events_status_id_start_date_index');
        $this->addIndex('sells', 'event_id', 'sells_event_id_index');
        $this->addIndex('sells', 'user_id', 'sells_user_id_index');
        $this->addIndex('sell_details', ['ticket_id', 'status'], 'sell_details_ticket_status_index');
        $this->addIndex('sell_details', ['event_id', 'user_id', 'status'], 'sell_details_event_user_status_index');
        $this->addIndex('sell_details', 'sell_id', 'sell_details_sell_id_index');
        $this->addIndex('tickets', ['event_id', 'is_package'], 'tickets_event_package_index');
        $this->addIndex('reviews', 'event_id', 'reviews_event_id_index');
    }

    public function down(): void
    {
        $this->dropIndex('events', 'events_status_id_id_index');
        $this->dropIndex('events', 'events_status_id_start_date_index');
        $this->dropIndex('sells', 'sells_event_id_index');
        $this->dropIndex('sells', 'sells_user_id_index');
        $this->dropIndex('sell_details', 'sell_details_ticket_status_index');
        $this->dropIndex('sell_details', 'sell_details_event_user_status_index');
        $this->dropIndex('sell_details', 'sell_details_sell_id_index');
        $this->dropIndex('tickets', 'tickets_event_package_index');
        $this->dropIndex('reviews', 'reviews_event_id_index');
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
