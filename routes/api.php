<?php

use App\Http\Controllers\Api\AdminReservationController;
use App\Http\Controllers\Api\PublicBookingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return new JsonResponse([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

Route::get('/rooms', [PublicBookingController::class, 'rooms']);
Route::get('/rooms/{room:slug}/availability', [PublicBookingController::class, 'availability']);
Route::post('/reservations', [PublicBookingController::class, 'store']);

Route::get('/admin/reservations', [AdminReservationController::class, 'index']);
Route::post('/admin/reservations', [AdminReservationController::class, 'store']);
Route::put('/admin/reservations/{reservation}', [AdminReservationController::class, 'update']);
Route::patch('/admin/reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel']);
Route::delete('/admin/reservations/{reservation}', [AdminReservationController::class, 'destroy']);
