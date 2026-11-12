<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->json('opening_hours')->nullable();
        });

        Schema::create('room_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('blocked_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['room_id', 'blocked_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn('opening_hours');
        });
    }
};
