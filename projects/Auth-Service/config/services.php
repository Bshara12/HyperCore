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

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST'),
        'port' => env('RABBITMQ_PORT'),
        'user' => env('RABBITMQ_USER'),
        'password' => env('RABBITMQ_PASSWORD'),
    ],

    'internal' => [
        'api_key' => env('INTERNAL_SERVICES_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Microservice
    | إعدادات التواصل مع خدمة الإشعارات
    |--------------------------------------------------------------------------
    */
    'notification' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://localhost:8005'),

        // التوكن الذي تستخدمه خدمة الـ Auth لإثبات هويتها
        // أمام خدمة الإشعارات (Service-to-Service Auth)
        'service_token' => env('NOTIFICATION_SERVICE_TOKEN'),
    ],
];
