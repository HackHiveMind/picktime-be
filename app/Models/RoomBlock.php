<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBlock extends Model
{
    protected $fillable = ['room_id', 'blocked_date', 'starts_at', 'ends_at', 'notes'];

    protected function casts(): array
    {
        return ['blocked_date' => 'date'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
