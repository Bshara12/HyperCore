<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Repositories\EloquentOperationRepositories;
use App\Repositories\EloquentServiceClientRepository;
use App\Repositories\EloquentSessionRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\OperationRepositoryInteface;
use App\Repositories\ServiceClientRepositoryInterface;
use App\Repositories\SessionRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\MessageBroker\RabbitMQPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(OperationRepositoryInteface::class, EloquentOperationRepositories::class);
        $this->app->bind(SessionRepositoryInterface::class, EloquentSessionRepository::class);
        $this->app->bind(ServiceClientRepositoryInterface::class, EloquentServiceClientRepository::class);

        $this->app->singleton(
            RabbitMQPublisher::class,
            fn () => new RabbitMQPublisher()
        );
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}
