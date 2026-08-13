<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    // تفعيل الـ UUIDs تلقائياً عند الإنشاء
    use HasUuids;

    protected $fillable = [
        'user_id',
        'user_email',
        'title',
        'body',
        'data',
        'channel',
        'source_service',
        'status',
        'error_message',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        // تحويل تلقائي من/إلى Enum
        'channel' => NotificationChannel::class,
        'status'  => NotificationStatus::class,
    ];

    // ═══════════════════════════════════════════════════
    // Query Scopes - لتبسيط الاستعلامات المتكررة
    // ═══════════════════════════════════════════════════

    /**
     * فلترة إشعارات مستخدم معين
     * الاستخدام: Notification::forUser($userId)->get()
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * فلترة الإشعارات الداخلية فقط
     * الاستخدام: Notification::inApp()->get()
     */
    public function scopeInApp(Builder $query): Builder
    {
        return $query->where('channel', NotificationChannel::IN_APP->value);
    }

    /**
     * فلترة الإشعارات غير المقروءة
     * الاستخدام: Notification::unread()->get()
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * فلترة حسب الحالة
     * الاستخدام: Notification::withStatus(NotificationStatus::FAILED)->get()
     */
    public function scopeWithStatus(Builder $query, NotificationStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    // ═══════════════════════════════════════════════════
    // Model Methods
    // ═══════════════════════════════════════════════════

    /** تحديد الإشعار كمقروء */
    public function markAsRead(): bool
    {
        // إذا كان مقروءاً بالفعل، لا داعي للتحديث
        if ($this->isRead()) {
            return false;
        }

        return $this->update(['read_at' => now()]);
    }

    /** تحديث حالة الإشعار إلى "مُرسَل" */
    public function markAsSent(): bool
    {
        return $this->update([
            'status'  => NotificationStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    /** تحديث حالة الإشعار إلى "فاشل" مع حفظ سبب الفشل */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status'        => NotificationStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /** التحقق إذا كان الإشعار مقروءاً */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}
