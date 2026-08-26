<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform health probes
    |--------------------------------------------------------------------------
    |
    | The services the operator dashboard reports on. Each entry names a
    | Laravel health endpoint (`health: '/up'` in that service's
    | bootstrap/app.php).
    |
    | Defaults are the docker-compose nginx hostnames on the `core` network,
    | so this works out of the box in the compose stack and stays overridable
    | per environment.
    |
    | The CMS itself is deliberately absent: this code runs inside it, so
    | probing its own nginx would spend an fpm worker waiting on another fpm
    | worker — and report "down" under load rather than the truth.
    |
    */

    'services' => [

        [
            'key' => 'auth',
            'label' => 'Auth Service',
            'url' => env('AUTH_HEALTH_URL', 'http://auth-nginx/up'),
        ],

        [
            'key' => 'logging',
            'label' => 'Logging Service',
            'url' => env('LOGGING_HEALTH_URL', 'http://logging-nginx/up'),
        ],

        [
            'key' => 'ecommerce',
            'label' => 'E-Commerce Service',
            'url' => env('ECOMMERCE_HEALTH_URL', 'http://ecommerce-nginx/up'),
        ],

        [
            'key' => 'booking',
            'label' => 'Booking Service',
            'url' => env('BOOKING_HEALTH_URL', 'http://booking-nginx/up'),
        ],

        [
            'key' => 'notification',
            'label' => 'Notification Service',
            'url' => env('NOTIFICATION_HEALTH_URL', 'http://notification-nginx/up'),
        ],
    ],

    /*
    | Seconds to wait per probe.
    |
    | 10s, not the 5s that seems generous: a cold PHP-FPM service takes ~3s to
    | answer /up, and five of them booting at once under a 5s ceiling reported
    | the whole platform as unreachable on the first load after a deploy.
    | Probes run pooled, so this is the wait for the slowest one, not the sum.
    */
    'health_timeout' => (int) env('PLATFORM_HEALTH_TIMEOUT', 10),
];
