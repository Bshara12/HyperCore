<?php

namespace App\Providers;

use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\Booking\Analytics\Repositories\EloquentBookingAnalyticsRepository;
use App\Domains\Booking\Repositories\Eloquent\EloquentBookingCancellationPolicyRepository;
use App\Domains\Booking\Repositories\Eloquent\EloquentBookingRepository;
use App\Domains\Booking\Repositories\Eloquent\EloquentResourceRepository;
use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Services\MessageBroker\RabbitMQPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResourceRepositoryInterface::class, EloquentResourceRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(BookingCancellationPolicyRepositoryInterface::class, EloquentBookingCancellationPolicyRepository::class);
        $this->app->bind(AnalyticsRepositoryInterface::class, EloquentBookingAnalyticsRepository::class);

        /*
         | Singleton: نفس instance الـ Publisher يُعاد استخدامه
         | بدلاً من فتح اتصال جديد بـ RabbitMQ في كل Observer
         */
        $this->app->singleton(
            RabbitMQPublisher::class,
            fn () => new RabbitMQPublisher()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | BookingObserver يراقب Booking model:
         |   created → booking.booking.created
         |   updated:
         |     status == cancelled   → booking.booking.cancelled
         |     start_at/end_at dirty → booking.booking.rescheduled
         */
        Booking::observe(BookingObserver::class);
    }
}
