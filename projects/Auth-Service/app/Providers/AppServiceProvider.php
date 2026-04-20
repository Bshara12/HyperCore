<?php

namespace App\Providers;

use App\Repositories\EloquentOperationRepositories;
use App\Repositories\EloquentProjectRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\OperationRepositoryInteface;
use App\Repositories\ProjectRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
        $this->app->bind(OperationRepositoryInteface::class, EloquentOperationRepositories::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
