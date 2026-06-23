<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\AdminRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoomController extends Controller
{
    private const BUSINESS_IDS = [
        'chisinau',
        'yellow',
    ];

    public function index(): JsonResponse
    {
        $rooms = Room::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room): array => $this->roomResponse($room))
            ->values();

        return new JsonResponse(['data' => $rooms]);
    }

    public function store(Request $request, AdminRoomService $rooms): JsonResponse
    {
        $validated = $this->validatedRoom($request);
        $room = $rooms->create($validated);

        return new JsonResponse(['data' => $this->roomResponse($room)], 201);
    }

    public function update(Request $request, Room $room, AdminRoomService $rooms): JsonResponse
    {
        $validated = $this->validatedRoom($request, required: false);
        $room = $rooms->update($room, $validated);

        return new JsonResponse(['data' => $this->roomResponse($room)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRoom(Request $request, bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'capacity' => [$presence, 'integer', 'min:1', 'max:500'],
            'business_id' => [$presence, 'string', Rule::in(self::BUSINESS_IDS)],
            'location' => ['nullable', 'string', 'max:255'],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => ['string', 'max:255'],
            'accent' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function roomResponse(Room $room): array
    {
        return [
            'id' => $room->slug,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'business_id' => $room->business_id,
            'location' => $room->location,
            'amenities' => $room->amenities ?? [],
            'accent' => $room->accent,
            'is_active' => $room->is_active,
        ];
    }
}
