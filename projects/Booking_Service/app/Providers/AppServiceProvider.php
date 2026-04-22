<?php

namespace App\Providers;

use App\Domains\Booking\Repositories\Eloquent\EloquentBookingCancellationPolicyRepository;
use App\Domains\Booking\Repositories\Eloquent\EloquentBookingRepository;
use App\Domains\Booking\Repositories\Eloquent\EloquentResourceRepository;
use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
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
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    //
  }
}
