<?php

use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\InternalNotificationController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\InternalSubscriptionController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\WebhookCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth.user', 'resolve.project'])->group(function () {
        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::get('/notifications/{id}/deliveries', [DeliveryController::class, 'indexByNotification']);

        Route::get('/preferences', [PreferenceController::class, 'index']);
        Route::put('/preferences', [PreferenceController::class, 'update']);

        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
        Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
    });

    Route::prefix('internal')->middleware(['auth.service', 'resolve.project'])->group(function () {
        Route::post('/notifications/system', [InternalNotificationController::class, 'storeSystem']);
        Route::post('/notifications/bulk', [InternalNotificationController::class, 'storeBulk']);

        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::get('/templates/{id}', [TemplateController::class, 'show']);
        Route::put('/templates/{id}', [TemplateController::class, 'update']);
        Route::patch('/templates/{id}/activate', [TemplateController::class, 'activate']);
        Route::patch('/templates/{id}/deactivate', [TemplateController::class, 'deactivate']);

        Route::get('/deliveries/{id}', [DeliveryController::class, 'show']);
        Route::post('/subscriptions/sync', [InternalSubscriptionController::class, 'sync']);
    });

    Route::post('/internal/deliveries/webhook/callback', [WebhookCallbackController::class, 'store']);
});
