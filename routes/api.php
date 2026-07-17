<?php

use App\Http\Controllers\Api\AdminReservationController;
use App\Http\Controllers\Api\AdminRoomController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\PublicBookingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', MetricsController::class);

Route::middleware('api.telemetry')->group(function (): void {
    Route::get('/health', function () {
        return new JsonResponse([
            'name' => config('app.name'),
            'status' => 'ok',
        ]);
    });

    Route::middleware('throttle:public-api')->group(function (): void {
        Route::get('/rooms', [PublicBookingController::class, 'rooms']);
        Route::get('/availability', [PublicBookingController::class, 'availabilityIndex']);
        Route::get('/rooms/{room:slug}/availability', [PublicBookingController::class, 'availability']);
    });

    Route::post('/reservations', [PublicBookingController::class, 'store'])
        ->middleware('throttle:reservations');

    Route::middleware('admin.api')->group(function (): void {
        Route::get('/admin/reservations', [AdminReservationController::class, 'index']);
        Route::post('/admin/reservations', [AdminReservationController::class, 'store']);
        Route::put('/admin/reservations/{reservation}', [AdminReservationController::class, 'update']);
        Route::patch('/admin/reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel']);
        Route::delete('/admin/reservations/{reservation}', [AdminReservationController::class, 'destroy']);
        Route::get('/admin/rooms', [AdminRoomController::class, 'index']);
        Route::post('/admin/rooms', [AdminRoomController::class, 'store']);
        Route::patch('/admin/rooms/{room:slug}', [AdminRoomController::class, 'update']);
        Route::put('/admin/rooms/{room:slug}', [AdminRoomController::class, 'update']);
    });
});
