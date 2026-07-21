<?php

use App\Http\Controllers\BookingAnalyticsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['resolve.project', 'auth.user', 'throttle:api.standard'])
  ->prefix('booking')
  ->group(function () {
    Route::prefix('resources')->group(function () {

      Route::get('/', [ResourceController::class, 'index'])->name('booking.resources.index');
      Route::get('/{resource}', [ResourceController::class, 'show'])->name('booking.resources.show');

      Route::post('/{resourceId}/bookings', [BookingController::class, 'resourceBookings'])
        ->middleware('permission:resource.viewBookings')
        ->name('booking.resources.bookings');

      Route::post('/{resourceId}/slots', [BookingController::class, 'slots'])
        ->name('booking.resources.slots');

      Route::middleware('throttle:api.heavy')->group(function () {
        Route::post('/', [ResourceController::class, 'store'])
          ->middleware('permission:resource.create')
          ->name('booking.resources.store');

        Route::patch('/{resource}', [ResourceController::class, 'update'])
          ->middleware('permission:resource.update')
          ->name('booking.resources.update');

        Route::delete('/{resource}', [ResourceController::class, 'destroy'])
          ->middleware('permission:resource.delete')
          ->name('booking.resources.destroy');

        Route::post('/{resource}/availability', [ResourceController::class, 'setAvailability'])
          ->middleware('permission:resource.update')
          ->name('booking.resources.availability.set');

        Route::post('/{resource}/policy', [ResourceController::class, 'setPolicy'])
          ->middleware('permission:resource.update')
          ->name('booking.resources.policy.set');
      });
    });
  });

Route::middleware('throttle:api.heavy')->group(function () {
  Route::post('/create', [BookingController::class, 'store'])->name('booking.client.create');
  Route::post('/cancel', [BookingController::class, 'cancel'])->name('booking.client.cancel');
  Route::post('/reschedule', [BookingController::class, 'reschedule'])->name('booking.client.reschedule');
});

Route::prefix('analytics')->group(function () {
  Route::get('/overview', [BookingAnalyticsController::class, 'overview'])->name('booking.analytics.overview');

  // تم تجهيز الأسماء للمسارات المستقبلية متى ما أردت تفعيلها
  // Route::get('/trend', [BookingAnalyticsController::class, 'trend'])->name('booking.analytics.trend');
  // Route::get('/resources', [BookingAnalyticsController::class, 'resourcePerformance'])->name('booking.analytics.resources');
  // Route::get('/cancellations', [BookingAnalyticsController::class, 'cancellations'])->name('booking.analytics.cancellations');
  // Route::get('/peak-times', [BookingAnalyticsController::class, 'peakTimes'])->name('booking.analytics.peak-times');
});
