<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Constraint is Postgres-only; SQLite (test suite) cannot express EXCLUDE.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Blocks any overlapping reservation for the same room/day regardless of
        // starts_at, closing the double-booking race that the unique index on
        // (room_id, reserved_date, starts_at) cannot catch for offset slots.
        // Cancelled reservations are exempt so a freed slot can be rebooked.
        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_no_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tsrange(reserved_date + starts_at, reserved_date + ends_at) WITH &&
            ) WHERE (status <> 'cancelled')
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
    }
};
