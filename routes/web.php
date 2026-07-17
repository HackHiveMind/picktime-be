<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminReservationWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? config('app.admin_dashboard_url', '/admin') : '/admin/login'));

Route::get('/admin/register', [AdminAuthController::class, 'showRegister'])->name('admin.register');
Route::post('/admin/register', [AdminAuthController::class, 'register'])->middleware('throttle:login');
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
Route::get('/admin/forgot-password', [AdminAuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/admin/forgot-password', [AdminAuthController::class, 'sendPasswordResetLink'])->name('password.email')->middleware('throttle:login');
Route::get('/admin/reset-password/{token}', [AdminAuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/admin/reset-password', [AdminAuthController::class, 'resetPassword'])->name('password.update');
Route::get('/admin/google', [AdminAuthController::class, 'redirectToGoogle'])->name('admin.google.redirect');
Route::get('/admin/google/callback', [AdminAuthController::class, 'handleGoogleCallback'])->name('admin.google.callback');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::post('/admin/reservations', [AdminReservationWebController::class, 'store'])->name('admin.reservations.store');
    Route::delete('/admin/reservations/{reservation}', [AdminReservationWebController::class, 'destroy'])->name('admin.reservations.destroy');
});
