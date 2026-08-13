<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case EMAIL     = 'email';      // إرسال بريد إلكتروني
    case IN_APP    = 'in_app';     // إشعار داخلي يُخزَّن في DB ويُقرأ عبر API
    case REAL_TIME = 'real_time';  // إشعار فوري عبر WebSocket (Reverb)

    /**
     * وصف مقروء لكل قناة (مفيد في Logs والـ Responses)
     */
    public function label(): string
    {
        return match($this) {
            self::EMAIL     => 'Email Notification',
            self::IN_APP    => 'In-App Notification',
            self::REAL_TIME => 'Real-Time Notification',
        };
    }

    /**
     * جلب جميع القيم كـ array (مفيد في Validation rules)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
