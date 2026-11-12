<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'capacity',
        'business_id',
        'location',
        'amenities',
        'accent',
        'image_url',
        'is_active',
        'opening_hours',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'amenities' => 'array',
            'is_active' => 'boolean',
            'opening_hours' => 'array',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }
}
