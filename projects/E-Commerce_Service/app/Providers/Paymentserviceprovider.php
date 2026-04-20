<?php

namespace App\Providers;

use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Gateways\PaypalGateway;
use App\Domains\Payment\Gateways\StripeGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->mergeConfigFrom(
      path: base_path('config/payment.php'),
      key: 'payment',
    );

    $this->app->bind(PaymentGatewayInterface::class, function () {

      $gateway = request('gateway')
        ?? config('payment.default');

      return match ($gateway) {
        'stripe'  => new StripeGateway(),
        'paypal'  => new PaypalGateway(),
        default   => throw new \InvalidArgumentException(
          "Unsupported payment gateway: [{$gateway}]"
        ),
      };
    });
  }

  public function boot(): void
  {
    //
  }
}
