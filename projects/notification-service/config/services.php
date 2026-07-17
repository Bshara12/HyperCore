<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'cms' => [
        'url' => env('CMS_URL'),
    ],

    'auth' => [
        'url' => env('Auth_URL'),
        // توكن خدمة الإشعارات لاستخدامه عند الاستعلام عن مستخدم بـ ID
        'service_token' => env('NOTIFICATION_SERVICE_TOKEN'),

        // المفتاح الداخلي الجديد لجلب بيانات المستخدمين
        'internal_api_key' => env('INTERNAL_SERVICES_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'notification_webhook' => [
        'url' => env('NOTIFICATION_WEBHOOK_URL', 'https://example.com/webhooks/notifications'),
        'secret' => env('NOTIFICATION_WEBHOOK_SECRET', 'super-secret-key'),
        'headers' => [
            'X-App-Source' => env('NOTIFICATION_WEBHOOK_SOURCE', 'notification-service'),
        ],
    ],


];
