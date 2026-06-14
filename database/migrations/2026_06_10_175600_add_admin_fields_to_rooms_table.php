<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('business_id')->default('chisinau')->after('capacity');
            $table->string('location')->nullable()->after('business_id');
            $table->json('amenities')->nullable()->after('location');
            $table->string('accent', 32)->default('#f7de05')->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['business_id', 'location', 'amenities', 'accent']);
        });
    }
};
