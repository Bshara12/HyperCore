<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\ScheduleServiceProvider;
use App\Providers\SearchServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    PaymentServiceProvider::class,
    ScheduleServiceProvider::class,
    SearchServiceProvider::class,
];
