<?php

namespace App\Services;

use App\Models\Room;
use App\Services\Telemetry\MetricsRecorder;

class AdminRoomService
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Room
    {
        return Room::query()->create([
            ...$attributes,
            'slug' => $this->uniqueSlug($attributes['name']),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Room $room, array $attributes): Room
    {
        $startedAt = microtime(true);
        $isToggleOnly = array_key_exists('is_active', $attributes) && count($attributes) === 1;
        $status = 'success';

        try {
            if ($isToggleOnly) {
                return $this->toggleBooking($room, (bool) $attributes['is_active']);
            }

            if (array_key_exists('name', $attributes) && $attributes['name'] !== $room->name) {
                $attributes['slug'] = $this->uniqueSlug($attributes['name'], $room);
            }

            $room->update($attributes);

            return $room->refresh();
        } catch (\Throwable $exception) {
            $status = 'error';

            throw $exception;
        } finally {
            if (array_key_exists('is_active', $attributes)) {
                request()->attributes->set('timing.admin_room_toggle_service', microtime(true) - $startedAt);

                $labels = [
                    'service' => 'admin_room',
                    'operation' => 'toggle_booking',
                    'room_id' => $room->slug,
                    'status' => $status,
                ];

                $this->metrics->increment('booking_room_toggle_total', $labels);
                $this->metrics->observe(
                    'booking_room_toggle_duration_seconds',
                    microtime(true) - $startedAt,
                    $labels,
                );
            }
        }
    }

    private function toggleBooking(Room $room, bool $isActive): Room
    {
        $startedAt = microtime(true);

        if ((bool) $room->is_active === $isActive) {
            request()->attributes->set('timing.admin_room_toggle_db', 0.0);

            return $room;
        }

        Room::query()
            ->whereKey($room->getKey())
            ->where('is_active', '!=', $isActive)
            ->update(['is_active' => $isActive]);

        request()->attributes->set('timing.admin_room_toggle_db', microtime(true) - $startedAt);

        $room->is_active = $isActive;

        return $room;
    }

    private function uniqueSlug(string $name, ?Room $ignoreRoom = null): string
    {
        $baseSlug = str($name)->slug()->toString() ?: 'room';
        $slug = $baseSlug;
        $index = 2;

        while (
            Room::query()
                ->where('slug', $slug)
                ->when($ignoreRoom, fn ($query) => $query->whereKeyNot($ignoreRoom->getKey()))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$index}";
            $index++;
        }

        return $slug;
    }
}
