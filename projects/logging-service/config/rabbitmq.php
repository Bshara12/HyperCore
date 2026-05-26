<?php

return [

    'host' => env('RABBITMQ_HOST', '127.0.0.1'),

    'port' => env('RABBITMQ_PORT', 5672),

    'user' => env('RABBITMQ_USER', 'guest'),

    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    'prefetch_size' => env('RABBITMQ_PREFETCH_SIZE', 0),

    'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 1),

    'global' => env('RABBITMQ_GLOBAL', false),

];