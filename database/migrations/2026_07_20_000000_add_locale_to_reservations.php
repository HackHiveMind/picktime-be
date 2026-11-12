<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('locale', 5)->default('ro');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
