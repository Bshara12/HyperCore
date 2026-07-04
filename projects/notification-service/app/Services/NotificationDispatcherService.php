<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Events\NotificationSentEvent;
use App\Mail\NotificationMail;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcherService
{
    /**
     * توجيه الإشعار إلى القناة المناسبة
     * يُستدعى من ProcessNotificationJob
     */
    public function dispatch(Notification $notification): void
    {
        match ($notification->channel) {
            NotificationChannel::EMAIL     => $this->sendEmail($notification),
            NotificationChannel::IN_APP    => $this->processInApp($notification),
            NotificationChannel::REAL_TIME => $this->broadcastRealTime($notification),
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Private Dispatch Methods
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * إرسال الإشعار عبر البريد الإلكتروني
     */
    private function sendEmail(Notification $notification): void
    {
        if (empty($notification->user_email)) {
            $notification->markAsFailed('Email address is missing.');
            Log::error('Email notification failed: missing email', ['id' => $notification->id]);
            return;
        }

        Mail::to($notification->user_email)->send(new NotificationMail($notification));
        $notification->markAsSent();

        Log::info('Email notification sent', [
            'id'    => $notification->id,
            'email' => $notification->user_email,
        ]);
    }

    /**
     * معالجة الإشعار الداخلي (In-App)
     * الإشعار مخزَّن بالفعل في DB، نقوم فقط بتحديث حالته
     */
    private function processInApp(Notification $notification): void
    {
        $notification->markAsSent();

        Log::info('In-app notification processed', ['id' => $notification->id]);
    }

    /**
     * بث الإشعار الفوري عبر WebSocket (Laravel Reverb)
     */
    private function broadcastRealTime(Notification $notification): void
    {
        // إطلاق الحدث الذي سيُبَثّ تلقائياً عبر Reverb إلى الـ Frontend
        event(new NotificationSentEvent($notification));
        $notification->markAsSent();

        Log::info('Real-time notification broadcast', [
            'id'      => $notification->id,
            'user_id' => $notification->user_id,
        ]);
    }
}
