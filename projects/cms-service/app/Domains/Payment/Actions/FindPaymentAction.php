<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Core\Actions\Action;
use App\Domains\Core\Traits\CircuitBreakerAware;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class FindPaymentAction extends Action
{
  use CircuitBreakerAware;
  public function __construct(
    private PaymentRepositoryInterface $repository
  ) {}

  protected function circuitServiceName(): string
  {
    return 'Payment-service';
  }

  public function execute(int $paymentId): Payment
  {
    return $this->runThroughCircuitBreaker(function () use ($paymentId) {
      $payment = $this->repository->findPayment($paymentId);

      throw_if(! $payment, \Exception::class, 'Payment not found.');
      throw_if(! $payment->isPaid(), \Exception::class, 'Payment is not paid.');

      return $payment;
    });
  }
}
