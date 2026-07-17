<?php

namespace App\Observers;

use App\Models\User;
use App\Services\MessageBroker\RabbitMQPublisher;

class UserObserver
{
    public function __construct(
        /*
         | استبدلنا NotificationApiClient بـ RabbitMQPublisher
         | لم نعد نحتاج HTTP Client هنا
         */
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق عند إنشاء مستخدم جديد
     * ننشر حدثاً واحداً يحمل كل البيانات
     * Notification Service يقرر نوع الإشعارات التي يرسلها بناءً على الحدث
     */
    public function created(User $user): void
    {
        $this->publisher->publish('auth.user.registered', [
            'user_id'    => (string) $user->id,
            'user_name'  => $user->name,
            'user_email' => $user->email,
            // إذا كان OTP موجوداً منذ الإنشاء نرسله مع الحدث
            'otp_code'   => $user->otp_code,
            'expires_at' => $user->otp_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * يُطلَق عند تحديث بيانات المستخدم
     * نهتم فقط بتغيّر otp_code
     */
    public function updated(User $user): void
    {
        if (!$user->isDirty('otp_code') || empty($user->otp_code)) {
            return;
        }

        $previousOtp = $user->getOriginal('otp_code');

        /*
         | الـ Routing Key يُحدد نوع الحدث:
         | auth.otp.sent   → OTP الإرسال الأول (القيمة القديمة كانت null)
         | auth.otp.resent → إعادة إرسال OTP (القيمة القديمة كانت موجودة)
         | Notification Service يستمع لكليهما لكن يُرسل رسالة مختلفة لكل منهما
         */
        $routingKey = is_null($previousOtp) ? 'auth.otp.sent' : 'auth.otp.resent';

        $this->publisher->publish($routingKey, [
            'user_id'    => (string) $user->id,
            'user_name'  => $user->name,
            'user_email' => $user->email,
            'otp_code'   => $user->otp_code,
            'expires_at' => $user->otp_expires_at?->toIso8601String(),
        ]);
    }
}
