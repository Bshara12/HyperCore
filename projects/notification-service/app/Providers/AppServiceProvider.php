<?php

namespace App\Providers;

use App\Services\Auth\AuthApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         | تسجيل AuthApiClient كـ Singleton
         | يعني نفس الـ instance سيُعاد استخدامه طوال الـ Request الواحد
         | بدلاً من إنشاء instance جديد في كل مرة يُحقَن فيها
         */
        $this->app->singleton(AuthApiClient::class, fn () => new AuthApiClient());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
