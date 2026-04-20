<?php

namespace App\Providers;

use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Gateways\PaypalGateway;
use App\Domains\Payment\Gateways\StripeGateway;
use App\Domains\Payment\Repositories\EloquentPaymentRepository;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->mergeConfigFrom(base_path('config/payment.php'), 'payment');

    $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);

    $this->app->bind(PaymentGatewayInterface::class, function ($app, array $params = []) {
      $gateway = $params['gatewayName']
        ?? $params['gatewayName']
        ?? config('payment.default');

      return match ($gateway) {
        'stripe' => new StripeGateway(),
        'paypal' => new PaypalGateway(),
        default  => throw new \InvalidArgumentException(
          "Unsupported payment gateway: [{$gateway}]"
        ),
      };
    });
  }
}
