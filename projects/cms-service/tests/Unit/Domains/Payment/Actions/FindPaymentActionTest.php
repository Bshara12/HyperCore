<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\FindPaymentAction;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Mockery;

beforeEach(function () {
    // 1. عمل Mock للـ Repository
    $this->repository = Mockery::mock(PaymentRepositoryInterface::class);

    // 2. عمل Mock للـ CircuitBreakerService
    // نستخدم makePartial حتى لا يتأثر بأي منطق داخلي قد يستدعيه الـ Trait
    $this->cbMock = Mockery::mock(\App\Domains\Core\Services\CircuitBreakerService::class)->makePartial();
    
    // 3. إجبار حاوية لارافيل على استخدام الـ Mock الخاص بنا
    // هذه الخطوة هي الأهم لأنها تضمن أن الـ Trait سيحصل على هذا الـ Mock
    $this->app->instance(\App\Domains\Core\Services\CircuitBreakerService::class, $this->cbMock);

    // 4. ضبط السلوك الافتراضي لمنع أي استعلام لقاعدة البيانات
    $this->cbMock->shouldReceive('canProceed')->byDefault()->andReturn(true);
    $this->cbMock->shouldReceive('reportSuccess')->byDefault();
    $this->cbMock->shouldReceive('reportFailure')->byDefault();

    // 5. إنشاء الـ Action
    $this->action = new FindPaymentAction($this->repository);
});

test('it returns payment when found and paid', function () {
  $payment = Mockery::mock(Payment::class);
  $payment->shouldReceive('isPaid')->once()->andReturn(true);

  $this->repository->shouldReceive('findPayment')
    ->once()
    ->with(123)
    ->andReturn($payment);

  $result = $this->action->execute(123);

  expect($result)->toBe($payment);
});

test('it throws exception when payment is not found', function () {
  $this->repository->shouldReceive('findPayment')
    ->once()
    ->with(999)
    ->andReturn(null);

  expect(fn() => $this->action->execute(999))
    ->toThrow(\Exception::class, 'Payment not found.');
});

test('it throws exception when payment exists but is not paid', function () {
  $payment = Mockery::mock(Payment::class);
  $payment->shouldReceive('isPaid')->once()->andReturn(false);

  $this->repository->shouldReceive('findPayment')
    ->once()
    ->with(123)
    ->andReturn($payment);

  expect(fn() => $this->action->execute(123))
    ->toThrow(\Exception::class, 'Payment is not paid.');
});

test('it uses the correct service name for circuit breaker', function () {
  // 1. إنشاء Mock لخدمة الـ CircuitBreaker
  $cbService = $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class);

  // 2. نتوقع أن يتم استدعاء canProceed مع اسم الخدمة الصحيح 'Payment-service'
  $cbService->shouldReceive('canProceed')
    ->once()
    ->with('Payment-service') // هنا الاختبار الفعلي للتابع protected
    ->andReturn(true);

  // 3. نتوقع أن يتم استدعاء reportSuccess عند النجاح
  $cbService->shouldReceive('reportSuccess')
    ->once()
    ->with('Payment-service');

  // 4. عمل Mock للـ Repository
  $repository = Mockery::mock(\App\Domains\Payment\Repositories\PaymentRepositoryInterface::class);
  $payment = Mockery::mock(\App\Models\Payment::class);
  $payment->shouldReceive('isPaid')->andReturn(true);

  $repository->shouldReceive('findPayment')->andReturn($payment);

  // 5. إنشاء الأكشن مع حقن الـ Repository
  $action = new \App\Domains\Payment\Actions\FindPaymentAction($repository);

  // 6. التنفيذ
  $action->execute(123);
});
