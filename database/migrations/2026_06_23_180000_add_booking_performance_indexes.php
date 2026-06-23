<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->index(
                ['room_id', 'reserved_date', 'status', 'starts_at', 'ends_at'],
                'reservations_booking_overlap_index',
            );
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'rooms_active_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_booking_overlap_index');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropIndex('rooms_active_name_index');
        });
    }
};
