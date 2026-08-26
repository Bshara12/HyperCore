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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
            fn () => new RabbitMQPublisher
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

        // 1. للمسارات القياسية الجلب والقراءة
        RateLimiter::for('api.standard', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // 2. للعمليات الثقيلة (الكتابة، التعديل، الحفظ والدفع)
        RateLimiter::for('api.heavy', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });

        // 3. لعمليات الذكاء الاصطناعي المكلفة
        RateLimiter::for('api.ai', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
