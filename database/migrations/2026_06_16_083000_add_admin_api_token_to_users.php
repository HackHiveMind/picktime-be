<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('admin_api_token_hash')->nullable()->after('remember_token');
            $table->timestamp('admin_api_token_expires_at')->nullable()->after('admin_api_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['admin_api_token_hash', 'admin_api_token_expires_at']);
        });
    }
};
