<?php

namespace App\Domain\Booking;

class BookingSlotRules
{
    public function firstSlotStart(): string
    {
        return '09:00';
    }

    public function lastSlotEnd(): string
    {
        return '21:00';
    }

    public function slotDurationMinutes(): int
    {
        return 60;
    }

    public function endForStart(string $start): string
    {
        $hour = (int) substr($start, 0, 2);

        return sprintf('%02d:00', $hour + 1);
    }

    /**
     * @return list<string>
     */
    public function slotStarts(): array
    {
        return [
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
        ];
    }
}
