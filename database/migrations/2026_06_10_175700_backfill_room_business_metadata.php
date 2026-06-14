<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rooms = [
            'imeet' => [
                'business_id' => 'chisinau',
                'location' => 'iHUB Chisinau',
                'amenities' => json_encode(['TV', 'Whiteboard', 'Video call', 'Cafea/ceai']),
                'accent' => '#74bd45',
            ],
            'loft' => [
                'business_id' => 'yellow',
                'location' => 'iHUB Yellow',
                'amenities' => json_encode(['Monitor', 'Whiteboard', 'Internet rapid', 'Bucatarie']),
                'accent' => '#f7de05',
            ],
            'green-conference' => [
                'business_id' => 'wfp-conference',
                'location' => 'iHUB - WFP Conference',
                'amenities' => json_encode(['Ecran 100 inch', 'Sistem audio/video', 'Flipchart', 'Suport IT']),
                'accent' => '#74bd45',
            ],
            'yellow-conference' => [
                'business_id' => 'yellow-conference',
                'location' => 'iHUB Yellow Conference',
                'amenities' => json_encode(['Proiector', 'Internet', 'Flipchart', 'Suport IT']),
                'accent' => '#f7de05',
            ],
        ];

        foreach ($rooms as $slug => $metadata) {
            DB::table('rooms')->where('slug', $slug)->update($metadata);
        }
    }

    public function down(): void
    {
        DB::table('rooms')->whereIn('slug', [
            'imeet',
            'loft',
            'green-conference',
            'yellow-conference',
        ])->update([
            'business_id' => 'chisinau',
            'location' => null,
            'amenities' => null,
            'accent' => '#f7de05',
        ]);
    }
};
