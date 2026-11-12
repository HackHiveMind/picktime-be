<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomScheduleController extends Controller
{
    public function show(Room $room, RoomAvailabilityService $availability): JsonResponse
    {
        return new JsonResponse(['data' => ['opening_hours' => $availability->hours($room), 'timezone' => 'Europe/Chisinau']]);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'opening_hours' => ['required', 'array', 'list', 'size:7'],
            'opening_hours.*' => ['nullable', 'array:open,close'],
        ]);

        foreach ($validated['opening_hours'] as $hours) {
            if ($hours !== null) {
                Validator::make($hours, [
                    'open' => ['required', 'date_format:H:i', 'after_or_equal:09:00'],
                    'close' => ['required', 'date_format:H:i', 'after:open', 'before_or_equal:21:00'],
                ])->validate();
            }
        }

        ksort($validated['opening_hours']);
        $validated['opening_hours'] = array_values($validated['opening_hours']);

        DB::transaction(function () use ($room, $validated): void {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            $lockedRoom->update($validated);
        });

        return new JsonResponse(['data' => $validated]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $blocks = RoomBlock::query()->with('room')
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('blocked_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('blocked_date', '<=', $date))
            ->orderBy('blocked_date')->orderBy('starts_at')->get();

        return new JsonResponse(['data' => $blocks->map(fn ($block) => $this->blockResponse($block))]);
    }

    public function storeBlock(Request $request): JsonResponse
    {
        return $this->saveBlock($request);
    }

    public function updateBlock(Request $request, RoomBlock $block): JsonResponse
    {
        return $this->saveBlock($request, $block);
    }

    public function destroyBlock(RoomBlock $block): Response
    {
        DB::transaction(function () use ($block): void {
            Room::query()->lockForUpdate()->findOrFail($block->room_id);
            $block->delete();
        });

        return new Response(null, 204);
    }

    private function saveBlock(Request $request, ?RoomBlock $block = null): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'slug')],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $saved = DB::transaction(function () use ($validated, $block): RoomBlock {
            $room = Room::query()->where('slug', $validated['room_id'])->lockForUpdate()->firstOrFail();
            $hasReservation = Reservation::query()->where('room_id', $room->id)
                ->whereDate('reserved_date', $validated['date'])->where('status', '!=', ReservationStatus::Cancelled)
                ->where('starts_at', '<', $validated['end_time'])->where('ends_at', '>', $validated['start_time'])->exists();
            $hasBlock = RoomBlock::query()->where('room_id', $room->id)
                ->whereDate('blocked_date', $validated['date'])
                ->when($block, fn ($query) => $query->whereKeyNot($block->id))
                ->where('starts_at', '<', $validated['end_time'])->where('ends_at', '>', $validated['start_time'])->exists();

            if ($hasReservation || $hasBlock) {
                throw ValidationException::withMessages(['start_time' => 'Intervalul se suprapune peste o rezervare sau un blocaj existent.']);
            }

            $saved = $block ?? new RoomBlock;
            $saved->fill([
                'room_id' => $room->id,
                'blocked_date' => $validated['date'],
                'starts_at' => $validated['start_time'],
                'ends_at' => $validated['end_time'],
                'notes' => $validated['notes'] ?? null,
            ])->save();

            return $saved->load('room');
        });

        return new JsonResponse(['data' => $this->blockResponse($saved)], $block ? 200 : 201);
    }

    private function blockResponse(RoomBlock $block): array
    {
        return [
            'id' => (string) $block->id,
            'room_id' => $block->room->slug,
            'date' => $block->blocked_date->format('Y-m-d'),
            'start_time' => substr($block->starts_at, 0, 5),
            'end_time' => substr($block->ends_at, 0, 5),
            'notes' => $block->notes ?? '',
            'created_at' => $block->created_at?->toISOString(),
        ];
    }
}
