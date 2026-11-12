<?php

namespace App\Services;

use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RoomAvailabilityService
{
    public function hours(Room $room): array
    {
        return $room->opening_hours ?? array_fill(0, 7, ['open' => '09:00', 'close' => '21:00']);
    }

    public function permits(Room $room, string $date, string $start, string $end): bool
    {
        $hours = $this->hours($room)[CarbonImmutable::parse($date)->dayOfWeekIso - 1];

        if ($hours === null || $start >= $end || $start < $hours['open'] || $end > $hours['close']) {
            return false;
        }

        return ! $room->blocks->contains(fn ($block): bool => $block->blocked_date->format('Y-m-d') === $date
            && substr($block->starts_at, 0, 5) < $end
            && substr($block->ends_at, 0, 5) > $start);
    }

    public function ensurePermitted(Room $room, string $date, string $start, string $end): void
    {
        $room->load(['blocks' => fn ($query) => $query->whereDate('blocked_date', $date)]);

        if (! $this->permits($room, $date, $start, $end)) {
            throw ValidationException::withMessages([
                'start_time' => 'Sala este închisă sau blocată în intervalul selectat.',
            ]);
        }
    }
}
