<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique(['room_id', 'reserved_date', 'starts_at']);
        });

        DB::statement("CREATE UNIQUE INDEX reservations_active_slot_unique ON reservations (room_id, reserved_date, starts_at) WHERE status <> 'cancelled'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX reservations_active_slot_unique');

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unique(['room_id', 'reserved_date', 'starts_at']);
        });
    }
};
