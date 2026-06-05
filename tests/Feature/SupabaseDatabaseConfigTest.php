<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupabaseDatabaseConfigTest extends TestCase
{
    public function test_database_defaults_are_ready_for_supabase_postgres(): void
    {
        $databaseConfig = file_get_contents(config_path('database.php'));

        $this->assertStringContainsString("'default' => env('DB_CONNECTION', 'pgsql')", $databaseConfig);
        $this->assertStringContainsString("'sslmode' => env('DB_SSLMODE', 'require')", $databaseConfig);
    }
}
