<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ─── Job Configuration ────────────────────────────────────────────────
    /** عدد المحاولات قبل اعتبار الـ Job فاشلاً نهائياً */
    public int $tries = 3;

    /** الوقت بالثواني بين كل محاولة (30 ثانية) */
    public array $backoff = [30, 60, 120];

    /** الحد الأقصى لوقت التنفيذ بالثواني */
    public int $timeout = 60;

    public function __construct(
        public readonly Notification $notification
    ) {}

    /**
     * تنفيذ الـ Job: إرسال الإشعار عبر القناة المحددة
     * يُحقَن NotificationDispatcherService تلقائياً من الـ Service Container
     */
    public function handle(NotificationDispatcherService $dispatcher): void
    {
        Log::info('Processing notification', [
            'id'      => $this->notification->id,
            'channel' => $this->notification->channel->value,
            'user_id' => $this->notification->user_id,
        ]);

        $dispatcher->dispatch($this->notification);
    }

    /**
     * يُنفَّذ عند فشل الـ Job بعد استنفاد جميع المحاولات
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job permanently failed', [
            'id'    => $this->notification->id,
            'error' => $exception->getMessage(),
        ]);

        // تحديث حالة الإشعار في قاعدة البيانات
        $this->notification->markAsFailed($exception->getMessage());
    }
}
