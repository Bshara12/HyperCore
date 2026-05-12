<?php

use App\Http\Controllers\BookingAnalyticsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['resolve.project', 'auth.user'])
  ->prefix('booking')
  ->group(function () {

    // ─── Resources (أدمن فقط) ─────────────────────────────────────────────
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/resources/{resource}', [ResourceController::class, 'show']);

    Route::post('/resources', [ResourceController::class, 'store'])
      ->middleware('permission:resource.create');


    Route::patch('/resources/{resource}', [ResourceController::class, 'update'])
      ->middleware('permission:resource.update');

    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy'])
      ->middleware('permission:resource.delete');

    Route::post('/resources/{resource}/availability', [ResourceController::class, 'setAvailability'])
      ->middleware('permission:resource.update');

    Route::post('/resources/{resource}/policy', [ResourceController::class, 'setPolicy'])
      ->middleware('permission:resource.update');

    Route::post('/resources/{resourceId}/bookings', [BookingController::class, 'resourceBookings'])
      ->middleware('permission:resource.viewBookings');

    Route::post('/resources/{resourceId}/slots', [BookingController::class, 'slots']);



    // client

    Route::post('/create', [BookingController::class, 'store']);
    Route::post('/cancel', [BookingController::class, 'cancel']);
    Route::post('/reschedule', [BookingController::class, 'reschedule']);
  });

Route::prefix('booking/analytics')
  ->middleware(['resolve.project', 'auth.user'])
  ->name('analytics.') // أضف هذا السطر هنا
  ->group(function () {
    Route::get('/overview', [BookingAnalyticsController::class, 'overview'])->name('overview');
    Route::get('/trend', [BookingAnalyticsController::class, 'trend'])->name('trend');
    Route::get('/resources', [BookingAnalyticsController::class, 'resourcePerformance'])->name('resources');
    Route::get('/cancellations', [BookingAnalyticsController::class, 'cancellations'])->name('cancellations');
    Route::get('/peak-times', [BookingAnalyticsController::class, 'peakTimes'])->name('peak-times');
  });
