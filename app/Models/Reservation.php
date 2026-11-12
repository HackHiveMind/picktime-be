<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
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
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
            'status' => ReservationStatus::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
