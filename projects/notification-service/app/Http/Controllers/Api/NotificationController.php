<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendBulkNotificationRequest;
use App\Http\Requests\SendNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * POST /api/v1/notifications/send
     * إرسال إشعار واحد - تُستدعى من الخدمات الأخرى
     */
    public function send(SendNotificationRequest $request): JsonResponse
    {
        // معلومات الخدمة المُرسِلة (أضافها ServiceAuthMiddleware)
        $service = $request->get('authenticated_service');

        $notification = $this->notificationService->create(
            $request->validated(),
            $service
        );

        // 202 Accepted: الطلب قُبِل وهو في قائمة الانتظار (لم يُرسَل بعد)
        return response()->json([
            'success' => true,
            'message' => 'Notification queued successfully.',
            'data'    => [
                'notification_id' => $notification->id,
                'channel'         => $notification->channel->value,
                'status'          => $notification->status->value,
            ],
        ], 202);
    }

    /**
     * POST /api/v1/notifications/send-bulk
     * إرسال إشعارات جماعية - تُستدعى من الخدمات الأخرى
     */
    public function sendBulk(SendBulkNotificationRequest $request): JsonResponse
    {
        $service       = $request->get('authenticated_service');
        $notifications = $this->notificationService->createBulk(
            $request->validated()['notifications'],
            $service
        );

        return response()->json([
            'success' => true,
            'message' => count($notifications) . ' notifications queued.',
            'data'    => [
                'notification_ids' => array_column($notifications, 'id'),
                'queued_count'     => count($notifications),
            ],
        ], 202);
    }
}
