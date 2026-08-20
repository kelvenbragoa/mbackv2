<?php

use App\Support\AccessCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_details', function (Blueprint $table) {
            $table->string('ticket_number', 16)->nullable()->after('id');
            $table->string('qrcode', 16)->nullable()->after('ticket_number');
        });

        Schema::table('customer_invites', function (Blueprint $table) {
            $table->string('invite_number', 16)->nullable()->after('id');
            $table->string('qrcode', 16)->nullable()->after('invite_number');
        });

        DB::table('sell_details')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];

                if (empty($row->ticket_number)) {
                    $updates['ticket_number'] = AccessCode::uniqueTicketNumber();
                }

                if (empty($row->qrcode)) {
                    $updates['qrcode'] = AccessCode::uniqueTicketQrcode();
                }

                if ($updates) {
                    DB::table('sell_details')->where('id', $row->id)->update($updates);
                }
            }
        });

        DB::table('customer_invites')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];

                if (empty($row->invite_number)) {
                    $updates['invite_number'] = AccessCode::uniqueInviteNumber();
                }

                if (empty($row->qrcode)) {
                    $updates['qrcode'] = AccessCode::uniqueInviteQrcode();
                }

                if ($updates) {
                    DB::table('customer_invites')->where('id', $row->id)->update($updates);
                }
            }
        });

        Schema::table('sell_details', function (Blueprint $table) {
            $table->unique('ticket_number');
            $table->unique('qrcode');
        });

        Schema::table('customer_invites', function (Blueprint $table) {
            $table->unique('invite_number');
            $table->unique('qrcode');
        });
    }

    public function down(): void
    {
        Schema::table('sell_details', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropUnique(['qrcode']);
            $table->dropColumn(['ticket_number', 'qrcode']);
        });

        Schema::table('customer_invites', function (Blueprint $table) {
            $table->dropUnique(['invite_number']);
            $table->dropUnique(['qrcode']);
            $table->dropColumn(['invite_number', 'qrcode']);
        });
    }
};
