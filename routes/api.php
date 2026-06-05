<?php

use App\Http\Controllers\Api\AdminReservationController;
use App\Http\Controllers\Api\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

Route::get('/rooms', [PublicBookingController::class, 'rooms']);
Route::get('/rooms/{room:slug}/availability', [PublicBookingController::class, 'availability']);
Route::post('/reservations', [PublicBookingController::class, 'store']);

Route::get('/admin/reservations', [AdminReservationController::class, 'index']);
Route::post('/admin/reservations', [AdminReservationController::class, 'store']);
