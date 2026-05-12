<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EcommerceAnalyticsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;


Route::middleware(['resolve.project', 'auth.user', 'ecommerce.enabled'])->prefix('ecommerce')->group(function () {

    // -------------------------
    // Offers
    // -------------------------
    Route::post('/offers', [OfferController::class, 'store'])
        ->middleware('permission:offer.create');

    Route::patch('/offers/{collectionSlug}', [OfferController::class, 'update'])
        ->middleware('permission:offer.update');

    Route::delete('/offers/{collectionSlug}', [OfferController::class, 'destroy'])
        ->middleware('permission:offer.delete');

    Route::post('/offers/{collectionSlug}/insert', [OfferController::class, 'addItems'])
        ->middleware('permission:offer.update');

    Route::delete('/offers/{collectionSlug}/items', [OfferController::class, 'removeItems'])
        ->middleware('permission:offer.update');

    Route::post('/offers/{collectionSlug}/deactivate', [OfferController::class, 'deactivate'])
        ->middleware('permission:offer.update');

    Route::post('/offers/{collectionSlug}/activate', [OfferController::class, 'activate'])
        ->middleware('permission:offer.update');

    Route::get('/offers', [OfferController::class, 'index']);
    Route::get('/offers/{collectionSlug}', [OfferController::class, 'show']);
    Route::post('/offers/{collectionSlug}/subscribe', [OfferController::class, 'subscribe']);

    // -------------------------
    // Products & Pricing
    // -------------------------
    Route::get('/products/{dataTypeSlug}', [ProductController::class, 'index']);
    Route::post('/pricing/calculate', [PricingController::class, 'calculate']);

    // -------------------------
    // Cart
    // -------------------------
    Route::post('/cart', [CartController::class, 'store']);
    Route::get('/cart', [CartController::class, 'show']);
    Route::put('/cart', [CartController::class, 'update']);
    Route::delete('/cart/items', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // -------------------------
    // Payments
    // -------------------------
    Route::post('/payments/pay', [PaymentController::class, 'charge']);
    Route::post('/payments/installment', [PaymentController::class, 'payInstallment']);
    // الاسترداد — إدارية
    Route::post('/payments/refund', [PaymentController::class, 'refund'])
        ->middleware('permission:payment.refund');

    // Route::post('/payments', [PaymentController::class, 'charge']);
    // الاسترداد — إدارية
    // Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
    //   ->middleware('permission:payment.refund');

    // -------------------------
    // Orders
    // -------------------------
    // خاصة بالمستخدم
    Route::post('/orders/from-cart', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);
    // إدارية
    Route::get('/allorders', [OrderController::class, 'adminIndex'])
        ->middleware('permission:order.viewAll');
    // checkout
    Route::post('/checkout', [CheckoutController::class, 'store']);

    // test
    // Route::get('/{collectionSlug}/products', [ProductController::class, 'index']);
    // Route::post('/pricing/calculate', [PricingController::class, 'calculate']);
    // Route::post('/offers/{collectionSlug}/subscribe', [OfferController::class, 'subscribe']);

    // Wishlist:
    Route::prefix('wishlists')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::get('/shared/{shareToken}', [WishlistController::class, 'showSharedWishlist']);

        Route::get('/{wishlistId}', [WishlistController::class, 'show']);
        Route::put('/{wishlistId}', [WishlistController::class, 'update']);
        Route::delete('/{wishlistId}', [WishlistController::class, 'destroy']);

        Route::post('/{wishlistId}/share-link', [WishlistController::class, 'generateShareLink']);

        Route::post('/{wishlistId}/items', [WishlistItemController::class, 'store']);
        Route::delete('/{wishlistId}/items/{itemId}', [WishlistItemController::class, 'destroy']);
        Route::post('/{wishlistId}/items/reorder', [WishlistItemController::class, 'reorder']);
        Route::post('/{wishlistId}/items/{itemId}/move-to-cart', [WishlistItemController::class, 'moveToCart']);
    });

    Route::patch('/orders/{orderId}/status', [OrderController::class, 'updateStatus'])
        ->middleware('permission:order.updateStatus');

    // -------------------------
    // Return Requests
    // -------------------------
    // خاصة بالمستخدم
    Route::post('/return-requests', [ReturnRequestController::class, 'store']);

    // Route::patch('/admin/return-requests/{id}', [ReturnRequestController::class, 'update']);
    // Route::get('/admin/return-requests', [ReturnRequestController::class, 'index']);

    // إدارية
    Route::get('/admin/return-requests', [ReturnRequestController::class, 'index'])
        ->middleware('permission:return.viewAll');

    Route::patch('/admin/return-requests/{id}', [ReturnRequestController::class, 'update'])
        ->middleware('permission:return.update');
});

Route::prefix('ecommerce/analytics')
    ->middleware(['resolve.project', 'auth.user'])
    ->group(function () {
        Route::get('/sales', [EcommerceAnalyticsController::class, 'salesSummary']);
        Route::get('/sales/trend', [EcommerceAnalyticsController::class, 'salesTrend']);
        Route::get('/products/top', [EcommerceAnalyticsController::class, 'topProducts']);
        Route::get('/offers', [EcommerceAnalyticsController::class, 'offersAnalytics']);
        Route::get('/customers/top', [EcommerceAnalyticsController::class, 'topCustomers']);
        Route::get('/returns', [EcommerceAnalyticsController::class, 'returnsAnalytics']);
    });

Route::get('/test', function () {
    return gethostname();
});
