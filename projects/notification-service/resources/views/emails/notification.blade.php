<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; }
        .header { background: #4F46E5; color: white; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .body { color: #374151; line-height: 1.6; font-size: 15px; }
        .footer { margin-top: 30px; font-size: 12px; color: #9CA3AF; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0">{{ $notification->title }}</h2>
        </div>
        <div class="body">
            <p>{{ $notification->body }}</p>

            {{-- إذا كانت هناك بيانات إضافية مثل رابط --}}
            @if(!empty($notification->data['action_url']))
                <p>
                    <a href="{{ $notification->data['action_url'] }}"
                       style="background:#4F46E5;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none">
                        {{ $notification->data['action_label'] ?? 'View Details' }}
                    </a>
                </p>
            @endif
        </div>
        <div class="footer">
            <p>تم إرسال هذا الإشعار من {{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
