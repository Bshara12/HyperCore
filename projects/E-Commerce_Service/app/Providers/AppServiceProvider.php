<?php

namespace App\Providers;

use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\E_Commerce\Analytics\Repositories\EloquentEcommerceAnalyticsRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Cart\EloquentCartItemRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Cart\EloquentCartRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Offers\OfferPriceRepositoryEloquent;
use App\Domains\E_Commerce\Repositories\Eloquent\Offers\OfferRepositorEloquent;
use App\Domains\E_Commerce\Repositories\Eloquent\Order\EloquentOrderItemRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Order\EloquentOrderRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\ReturnRequest\EloquentReturnRequestRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Wishlist\WishlistItemRepository;
use App\Domains\E_Commerce\Repositories\Eloquent\Wishlist\WishlistRepository;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistRepositoryInterface;
use App\Domains\Payment\Repositories\EloquentPaymentRepository;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OfferRepositoryInterface::class, OfferRepositorEloquent::class);
        $this->app->bind(OfferPriceRepositoryInterface::class, OfferPriceRepositoryEloquent::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartItemRepositoryInterface::class, EloquentCartItemRepository::class);
        // $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(OrderItemRepositoryInterface::class, EloquentOrderItemRepository::class);
        $this->app->bind(ReturnRequestRepositoryInterface::class, EloquentReturnRequestRepository::class);
        $this->app->bind(AnalyticsRepositoryInterface::class, EloquentEcommerceAnalyticsRepository::class);
        $this->app->bind(WishlistRepositoryInterface::class, WishlistRepository::class);
        $this->app->bind(WishlistItemRepositoryInterface::class, WishlistItemRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
