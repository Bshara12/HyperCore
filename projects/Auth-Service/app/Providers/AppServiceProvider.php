<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repositories\EloquentOperationRepositories;
use App\Repositories\EloquentUserRepository;
use App\Repositories\OperationRepositoryInteface;
use App\Repositories\UserRepositoryInterface;
use App\Services\MessageBroker\RabbitMQPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(OperationRepositoryInteface::class, EloquentOperationRepositories::class);
        /*
         | استبدلنا NotificationApiClient بـ RabbitMQPublisher
         | Singleton لأننا لا نريد إنشاء اتصال جديد بـ RabbitMQ في كل مرة
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
         | UserObserver الآن يتولى كل الأحداث المتعلقة بالإشعارات:
         |
         | created → welcome (دائماً) + otp (إذا كان موجوداً لحظة الإنشاء)
         | updated → otp أول مرة | otp إعادة إرسال
         |
         | لا يوجد OtpObserver لأنه لا يوجد Otp Model منفصل
         */
        User::observe(UserObserver::class);
    }
}
