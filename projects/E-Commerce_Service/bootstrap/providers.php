<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PaymentServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    EventServiceProvider::class,
];
