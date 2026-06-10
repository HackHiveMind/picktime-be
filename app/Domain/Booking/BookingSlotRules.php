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
        [$hour, $minute] = array_map('intval', explode(':', $start));
        $endMinutes = ($hour * 60) + $minute + $this->slotDurationMinutes();

        return sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
    }

    /**
     * @return list<string>
     */
    public function slotStarts(): array
    {
        $starts = [];
        $startMinutes = $this->timeToMinutes($this->firstSlotStart());
        $lastStartMinutes = $this->timeToMinutes($this->lastSlotEnd()) - $this->slotDurationMinutes();

        for ($minutes = $startMinutes; $minutes <= $lastStartMinutes; $minutes += 30) {
            $starts[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return $starts;
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }
}
