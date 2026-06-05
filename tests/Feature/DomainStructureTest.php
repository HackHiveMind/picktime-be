<?php

namespace Tests\Feature;

use App\Domain\Booking\BookingSlotRules;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_and_reservations_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('rooms'));
        $this->assertTrue(Schema::hasColumns('rooms', [
            'id',
            'name',
            'slug',
            'capacity',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('reservations'));
        $this->assertTrue(Schema::hasColumns('reservations', [
            'id',
            'room_id',
            'status',
            'reserved_date',
            'starts_at',
            'ends_at',
            'first_name',
            'last_name',
            'email',
            'phone',
            'notes',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_room_has_many_reservations(): void
    {
        $room = Room::factory()->create();
        $reservation = Reservation::factory()->for($room)->create();

        $this->assertTrue($room->reservations->contains($reservation));
        $this->assertTrue($reservation->room->is($room));
    }

    public function test_reservation_statuses_are_explicit(): void
    {
        $this->assertSame('pending', ReservationStatus::Pending->value);
        $this->assertSame('confirmed', ReservationStatus::Confirmed->value);
        $this->assertSame('cancelled', ReservationStatus::Cancelled->value);
    }

    public function test_booking_slot_rules_use_one_hour_slots_between_09_and_21(): void
    {
        $rules = new BookingSlotRules();

        $this->assertSame('09:00', $rules->firstSlotStart());
        $this->assertSame('21:00', $rules->lastSlotEnd());
        $this->assertSame(60, $rules->slotDurationMinutes());
        $this->assertSame([
            '09:00',
            '10:00',
            '11:00',
            '12:00',
            '13:00',
            '14:00',
            '15:00',
            '16:00',
            '17:00',
            '18:00',
            '19:00',
            '20:00',
        ], $rules->slotStarts());
    }
}

