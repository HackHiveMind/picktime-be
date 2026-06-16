<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')
            ->where('business_id', 'yellow-conference')
            ->update([
                'business_id' => 'yellow',
                'location' => 'iHUB Yellow',
            ]);

        DB::table('rooms')
            ->where('business_id', 'wfp-conference')
            ->update([
                'business_id' => 'chisinau',
                'location' => 'iHUB Chisinau',
            ]);
    }

    public function down(): void
    {
        DB::table('rooms')
            ->where('slug', 'yellow-conference')
            ->update([
                'business_id' => 'yellow-conference',
                'location' => 'iHUB Yellow Conference',
            ]);

        DB::table('rooms')
            ->where('slug', 'green-conference')
            ->update([
                'business_id' => 'wfp-conference',
                'location' => 'iHUB - WFP Conference',
            ]);
    }
};
