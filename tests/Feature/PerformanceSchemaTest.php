<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_overlap_queries_have_composite_index(): void
    {
        $this->assertTableHasIndexColumns('reservations', [
            'room_id',
            'reserved_date',
            'status',
            'starts_at',
            'ends_at',
        ]);
    }

    public function test_active_room_listing_has_composite_index(): void
    {
        $this->assertTableHasIndexColumns('rooms', [
            'is_active',
            'name',
        ]);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertTableHasIndexColumns(string $table, array $columns): void
    {
        $indexes = Schema::getIndexes($table);

        $hasIndex = collect($indexes)->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === $columns,
        );

        $this->assertTrue(
            $hasIndex,
            sprintf('Expected %s to have index on columns: %s', $table, implode(', ', $columns)),
        );
    }
}
