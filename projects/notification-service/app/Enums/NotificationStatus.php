<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending'; // في انتظار المعالجة بواسطة Queue Worker
    case SENT    = 'sent';    // تم الإرسال بنجاح
    case FAILED  = 'failed';  // فشل الإرسال بعد استنفاد جميع المحاولات

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
