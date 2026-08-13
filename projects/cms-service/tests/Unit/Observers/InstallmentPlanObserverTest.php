<?php

use App\Observers\InstallmentPlanObserver;
use App\Models\InstallmentPlan;
use App\Services\MessageBroker\RabbitMQPublisher;

beforeEach(function () {
  $this->publisher = Mockery::mock(RabbitMQPublisher::class);
  $this->observer = new InstallmentPlanObserver($this->publisher);
});

afterEach(function () {
  Mockery::close();
});

test('it returns early if payment or user_id is missing', function () {
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->shouldReceive('loadMissing')->once()->with('payment');
  $plan->payment = null; // لا يوجد علاقة دفع أو مستخدم

  $this->publisher->shouldNotReceive('publish');

  $this->observer->updated($plan);
});

test('it publishes completed event when status changes to completed', function () {
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->shouldReceive('loadMissing')->once()->with('payment');
  $plan->shouldReceive('isDirty')->with('status')->andReturn(true);

  $plan->status = InstallmentPlan::STATUS_COMPLETED;
  $plan->payment_id = 10;
  $plan->total_installments = 5;

  // محاكاة كائن الدفع المرتبط
  $payment = new \stdClass();
  $payment->user_id = 42;
  $payment->amount = 500;
  $payment->currency = 'USD';
  $plan->payment = $payment;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.installment.completed', [
      'user_id'            => '42',
      'payment_id'         => 10,
      'total_installments' => 5,
      'total_amount'       => 500,
      'currency'           => 'USD',
    ]);

  $this->observer->updated($plan);
});

test('it publishes defaulted event when status changes to defaulted', function () {
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->shouldReceive('loadMissing')->once()->with('payment');
  // لكي يتجاوز شرط الـ completed، نجعل isDirty('status') صحيحة والـ status هو defaulted
  $plan->shouldReceive('isDirty')->with('status')->andReturn(true);

  $plan->status = InstallmentPlan::STATUS_DEFAULTED;
  $plan->payment_id = 15;
  $plan->paid_installments = 2;
  $plan->total_installments = 6;

  $plan->shouldReceive('remainingInstallments')->andReturn(4);
  $plan->shouldReceive('remainingAmount')->andReturn(300);

  $payment = new \stdClass();
  $payment->user_id = 99;
  $payment->currency = 'EUR';
  $plan->payment = $payment;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.installment.defaulted', [
      'user_id'                => '99',
      'payment_id'             => 15,
      'paid_installments'      => 2,
      'total_installments'     => 6,
      'remaining_installments' => 4,
      'remaining_amount'       => 300,
      'currency'               => 'EUR',
    ]);

  $this->observer->updated($plan);
});

test('it publishes paid event when paid_installments is dirty', function () {
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->shouldReceive('loadMissing')->once()->with('payment');

  // شروط الـ status غير محققة
  $plan->shouldReceive('isDirty')->with('status')->andReturn(false);
  // شرط الـ paid_installments محقق
  $plan->shouldReceive('isDirty')->with('paid_installments')->andReturn(true);

  $plan->payment_id = 20;
  $plan->paid_installments = 3;
  $plan->total_installments = 10;
  $plan->installment_amount = 100;
  $plan->next_due_date = null;

  $plan->shouldReceive('remainingInstallments')->andReturn(7);
  $plan->shouldReceive('remainingAmount')->andReturn(700);

  $payment = new \stdClass();
  $payment->user_id = 7;
  $payment->currency = 'USD';
  $plan->payment = $payment;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.installment.paid', [
      'user_id'                => '7',
      'payment_id'             => 20,
      'installment_number'     => 3,
      'total_installments'     => 10,
      'remaining_installments' => 7,
      'installment_amount'     => 100,
      'remaining_amount'       => 700,
      'currency'               => 'USD',
      'next_due_date'          => null,
    ]);

  $this->observer->updated($plan);
});
