<?php

use App\Http\Controllers\Api\InAppNotificationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\BroadcastController;
use Illuminate\Support\Facades\Route;

/*
|─────────────────────────────────────────────────────────────────────────────
| Broadcasting Auth Route
| يجب تسجيله قبل أي route groups لإعطائه الأولوية
|─────────────────────────────────────────────────────────────────────────────
*/
Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware('auth.user');

/*
|─────────────────────────────────────────────────────────────────────────────
| Service-to-Service Routes
| محمية بـ ServiceAuthMiddleware
| تُستدعى من: Auth, E-commerce, Booking, CMS... إلخ
|─────────────────────────────────────────────────────────────────────────────
*/
Route::middleware('auth.service')
    ->prefix('v1/notifications')
    ->name('notifications.')
    ->group(function () {
        Route::post('/send', [NotificationController::class, 'send'])
            ->name('send');

        Route::post('/send-bulk', [NotificationController::class, 'sendBulk'])
            ->name('send-bulk');
    });

/*
|─────────────────────────────────────────────────────────────────────────────
| User-Facing Routes
| محمية بـ UserAuthMiddleware
| يصل إليها المستخدم مباشرة لإدارة إشعاراته الداخلية
|─────────────────────────────────────────────────────────────────────────────
*/
Route::middleware('auth.user')
    ->prefix('v1/in-app-notifications')
    ->name('in-app-notifications.')
    ->group(function () {
        Route::get('/', [InAppNotificationController::class, 'index'])
            ->name('index');

        Route::get('/unread-count', [InAppNotificationController::class, 'unreadCount'])
            ->name('unread-count');

        Route::put('/read-all', [InAppNotificationController::class, 'markAllAsRead'])
            ->name('read-all');

        Route::put('/{id}/read', [InAppNotificationController::class, 'markAsRead'])
            ->name('read');

        Route::delete('/{id}', [InAppNotificationController::class, 'destroy'])
            ->name('destroy');
    });
