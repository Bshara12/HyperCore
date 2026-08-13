<?php

use App\Observers\PaymentObserver;
use App\Models\Payment;
use App\Services\MessageBroker\RabbitMQPublisher;

beforeEach(function () {
  $this->publisher = Mockery::mock(RabbitMQPublisher::class);
  $this->observer = new PaymentObserver($this->publisher);
});

afterEach(function () {
  Mockery::close();
});

test('it returns early if payment status is not dirty', function () {
  $payment = Mockery::mock(Payment::class)->makePartial();
  $payment->shouldReceive('isDirty')->with('status')->andReturn(false);

  $this->publisher->shouldNotReceive('publish');

  $this->observer->updated($payment);
});

test('it returns early if new status is not in tracked statuses', function () {
  $payment = Mockery::mock(Payment::class)->makePartial();
  $payment->shouldReceive('isDirty')->with('status')->andReturn(true);
  $payment->status = 'processing'; // حالة غير مشموله بالمتتبع

  $this->publisher->shouldNotReceive('publish');

  $this->observer->updated($payment);
});

test('it publishes event when payment status changes to paid', function () {
  $payment = Mockery::mock(Payment::class)->makePartial();
  $payment->shouldReceive('isDirty')->with('status')->andReturn(true);
  $payment->shouldReceive('getOriginal')->with('status')->andReturn('pending');

  $payment->status = Payment::STATUS_PAID;
  $payment->user_id = 1;
  $payment->id = 100;
  $payment->amount = 150.00;
  $payment->currency = 'USD';
  $payment->gateway = 'stripe';
  $payment->payment_type = 'full';

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.payment.paid', [
      'user_id'      => '1',
      'payment_id'   => 100,
      'amount'       => 150.00,
      'currency'     => 'USD',
      'gateway'      => 'stripe',
      'payment_type' => 'full',
      'old_status'   => 'pending',
      'new_status'   => Payment::STATUS_PAID,
    ]);

  $this->observer->updated($payment);
});

test('it publishes event when payment status changes to failed', function () {
  $payment = Mockery::mock(Payment::class)->makePartial();
  $payment->shouldReceive('isDirty')->with('status')->andReturn(true);
  $payment->shouldReceive('getOriginal')->with('status')->andReturn('pending');

  $payment->status = Payment::STATUS_FAILED;
  $payment->user_id = 2;
  $payment->id = 101;
  $payment->amount = 200.00;
  $payment->currency = 'EUR';
  $payment->gateway = 'paypal';
  $payment->payment_type = 'installment';

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.payment.failed', [
      'user_id'      => '2',
      'payment_id'   => 101,
      'amount'       => 200.00,
      'currency'     => 'EUR',
      'gateway'      => 'paypal',
      'payment_type' => 'installment',
      'old_status'   => 'pending',
      'new_status'   => Payment::STATUS_FAILED,
    ]);

  $this->observer->updated($payment);
});

test('it publishes event when payment status changes to refunded', function () {
  $payment = Mockery::mock(Payment::class)->makePartial();
  $payment->shouldReceive('isDirty')->with('status')->andReturn(true);
  $payment->shouldReceive('getOriginal')->with('status')->andReturn('paid');

  $payment->status = Payment::STATUS_REFUNDED;
  $payment->user_id = 3;
  $payment->id = 102;
  $payment->amount = 75.50;
  $payment->currency = 'USD';
  $payment->gateway = 'stripe';
  $payment->payment_type = 'full';

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.payment.refunded', [
      'user_id'      => '3',
      'payment_id'   => 102,
      'amount'       => 75.50,
      'currency'     => 'USD',
      'gateway'      => 'stripe',
      'payment_type' => 'full',
      'old_status'   => 'paid',
      'new_status'   => Payment::STATUS_REFUNDED,
    ]);

  $this->observer->updated($payment);
});
