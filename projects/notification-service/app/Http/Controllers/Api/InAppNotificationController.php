<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    /**
     * GET /api/v1/in-app-notifications
     * جلب إشعارات المستخدم الداخلية مع Pagination
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->get('authenticated_user');

        $notifications = Notification::forUser($user['id'])
            ->inApp()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    /**
     * GET /api/v1/in-app-notifications/unread-count
     * عدد الإشعارات غير المقروءة (لعرضها على أيقونة الجرس مثلاً)
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user  = $request->get('authenticated_user');
        $count = Notification::forUser($user['id'])->inApp()->unread()->count();

        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => $count],
        ]);
    }

    /**
     * PUT /api/v1/in-app-notifications/{id}/read
     * تحديد إشعار محدد كمقروء
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->get('authenticated_user');

        // نتأكد أن الإشعار يعود للمستخدم الحالي
        $notification = Notification::forUser($user['id'])->inApp()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * PUT /api/v1/in-app-notifications/read-all
     * تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->get('authenticated_user');

        Notification::forUser($user['id'])
            ->inApp()
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * DELETE /api/v1/in-app-notifications/{id}
     * حذف إشعار
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user         = $request->get('authenticated_user');
        $notification = Notification::forUser($user['id'])->inApp()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }
}
