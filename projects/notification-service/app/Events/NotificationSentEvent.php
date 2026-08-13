<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Notification $notification
    ) {}

    /**
     * تحديد القناة التي سيُبَثّ عليها الإشعار
     * كل مستخدم له قناة خاصة: private-user.{user_id}
     * "private" تعني أن الاشتراك يتطلب تفويضاً (Authorization)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->notification->user_id),
        ];
    }

    /**
     * اسم الحدث الذي سيستمع إليه الـ Frontend
     * مثال: Echo.private('user.123').listen('.notification.sent', callback)
     */
    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    /**
     * البيانات التي ستُرسَل مع الحدث إلى الـ Frontend
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'title'      => $this->notification->title,
            'body'       => $this->notification->body,
            'data'       => $this->notification->data,
            'created_at' => $this->notification->created_at->toIso8601String(),
        ];
    }
}
