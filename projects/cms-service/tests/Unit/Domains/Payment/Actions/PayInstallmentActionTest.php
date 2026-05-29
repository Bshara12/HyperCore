<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\PayInstallmentAction;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = Mockery::mock(PaymentRepositoryInterface::class)->shouldIgnoreMissing();
  $this->action = new PayInstallmentAction($this->repository);

  $this->gatewayMock = Mockery::mock(PaymentGatewayInterface::class);

  // الحل السحري: استخدام bind مع Closure يستقبل البارامترات ويعيد الـ Mock دائماً
  $this->app->bind(PaymentGatewayInterface::class, function ($app, $parameters) {
    return $this->gatewayMock;
  });
});

// 1. اختبار: فشل الدفع إذا لم يوجد Payment
test('it throws exception if payment not found', function () {
  $this->repository->shouldReceive('findPayment')->andReturn(null);
  $dto = new PayInstallmentDTO(999, 'wallet', 'USD');

  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'Payment not found.');
});

// 2. اختبار: فشل الدفع إذا لم يكن النوع Installment
test('it throws exception if payment is not an installment', function () {
  // 2. غيرنا 'regular' إلى 'full' لتتوافق مع الـ Enum الخاص بك
  $payment = Payment::factory()->create(['payment_type' => 'full']);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD');
  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'This payment is not an installment plan.');
});

// 3. اختبار: فشل الدفع إذا لم توجد خطة تقسيط
test('it throws exception if installment plan is missing', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD');
  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'Installment plan not found.');
});

test('it throws exception if plan is completed', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);

  // الحل: إنشاء Mock جزئي يسمح لنا بالتلاعب بدالة isCompleted()
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->shouldReceive('isCompleted')->andReturn(true);

  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD');
  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'All installments have been paid.');
});

test('it throws exception if plan is defaulted', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);

  // الحل: إنشاء Mock جزئي لنتمكن من تغيير قيمة status يدوياً
  $plan = Mockery::mock(InstallmentPlan::class)->makePartial();
  $plan->status = 'defaulted';
  $plan->shouldReceive('isCompleted')->andReturn(false); // نضمن أنها غير مكتملة لتصل للتحقق التالي

  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD');
  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'This installment plan is defaulted.');
});

test('it pays via gateway successfully and increments installments', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);

  // إنشاء خطة حقيقية بالـ Factory لضمان وجود حقول المبالغ والأعداد
  $plan = InstallmentPlan::factory()->create([
    'payment_id' => $payment->id,
    'status' => 'active',
    'total_installments' => 5,
    'paid_installments' => 1,
  ]);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  // محاكاة استجابة بوابة الدفع بالنجاح
  $this->gatewayMock->shouldReceive('charge')->once()->andReturn([
    'success' => true,
    'transaction_id' => 'tx_gateway_123',
    'raw' => ['status' => 'succeeded']
  ]);

  // نجهز كائن الخطة المحدثة التي سيعيدها الـ repository بعد الزيادة (لم تكتمل بعد)
  $updatedPlan = InstallmentPlan::factory()->make([
    'status' => 'active',
    'total_installments' => 5,
    'paid_installments' => 2,
  ]);

  $this->repository->shouldReceive('incrementPaidInstallments')->once()->andReturn($updatedPlan);
  $this->repository->shouldNotReceive('markPlanCompleted');

  $dto = new PayInstallmentDTO($payment->id, 'stripe', 'USD');
  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['payment_method'])->toBe('gateway')
    ->and($result['transaction_id'])->toBe('tx_gateway_123');
});

// 2. اختبار: نجاح الدفع وإغلاق الخطة بالكامل عند سداد آخر قسط
test('it completes the plan when installments are finished', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);

  // الخطة متبقي عليها قسط واحد وتكتمل
  $plan = InstallmentPlan::factory()->create([
    'payment_id' => $payment->id,
    'status' => 'active',
    'total_installments' => 5,
    'paid_installments' => 4,
  ]);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $this->gatewayMock->shouldReceive('charge')->once()->andReturn([
    'success' => true,
    'transaction_id' => 'tx_final_123',
    'raw' => []
  ]);

  // هنا نستخدم Mock مرن جداً فقط للخطة المعاد إرجاعها لضمان تفعيل شرط isCompleted
  $completedPlan = Mockery::mock(InstallmentPlan::class)->makePartial()->shouldIgnoreMissing();
  $completedPlan->shouldReceive('isCompleted')->andReturn(true);
  $completedPlan->shouldReceive('remainingInstallments')->andReturn(0);
  $completedPlan->status = 'completed';

  $this->repository->shouldReceive('incrementPaidInstallments')->once()->andReturn($completedPlan);

  // التأكد من استدعاء دوال الحفظ والإغلاق في الـ Repository
  $this->repository->shouldReceive('markPlanCompleted')->once()->with($completedPlan);
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PAID);

  $dto = new PayInstallmentDTO($payment->id, 'stripe', 'USD');
  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['plan_status'])->toBe('completed');
});

// 3. اختبار: التعامل مع فشل بوابة الدفع وتسجيل المعاملة كفاشلة
test('it handles gateway failure correctly', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);
  $plan = InstallmentPlan::factory()->create([
    'payment_id' => $payment->id,
    'status' => 'active',
    'total_installments' => 3,
    'paid_installments' => 0,
  ]);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  // محاكاة فشل البوابة (مثلاً رفض البطاقة)
  $this->gatewayMock->shouldReceive('charge')->once()->andReturn([
    'success' => false,
    'transaction_id' => 'tx_failed_123',
    'raw' => ['error' => 'card_declined']
  ]);

  // التأكد من إنشاء المعاملة في الـ repository (بغض النظر عن الحالة الدقيقة للـ Enum)
  $this->repository->shouldReceive('createGatewayTransaction')->once();

  // التأكد من عدم تحديث أو زيادة الأقساط نهائياً
  $this->repository->shouldNotReceive('incrementPaidInstallments');

  $dto = new PayInstallmentDTO($payment->id, 'stripe', 'USD');
  $result = $this->action->execute($dto);

  expect($result['success'])->toBeFalse();
});

// 1. اختبار: فشل العملية إذا لم يتم العثور على محفظة المستخدم
test('it throws exception if wallet not found', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);
  $plan = InstallmentPlan::factory()->create(['payment_id' => $payment->id, 'status' => 'active']);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);
  $this->repository->shouldReceive('findWalletByUserId')->once()->andReturn(null);

  // استخدام المسار الكامل للموديل لضمان قراءة الـ Factory بشكل صحيح
  $toWallet = \App\Models\Wallet::factory()->make(['id' => 2]);
  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD', toWallet: $toWallet);

  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'Wallet not found.');
});

// 2. اختبار: فشل العملية إذا كان رصيد المحفظة غير كافٍ
test('it throws exception if wallet balance is insufficient', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);
  $plan = InstallmentPlan::factory()->create(['payment_id' => $payment->id, 'status' => 'active']);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $fromWallet = Mockery::mock(\App\Models\Wallet::class)->makePartial();
  $fromWallet->balance = 10.0;
  $fromWallet->shouldReceive('hasSufficientBalance')->once()->andReturn(false);

  $this->repository->shouldReceive('findWalletByUserId')->once()->andReturn($fromWallet);

  // استخدام المسار الكامل هنا أيضاً
  $toWallet = \App\Models\Wallet::factory()->make(['id' => 2]);
  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD', toWallet: $toWallet);

  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'Insufficient wallet balance.');
});

// 3. اختبار: نجاح الدفع عبر المحفظة وحسم/إيداع المبالغ (دون إكمال الخطة)
test('it pays via wallet successfully and shifts balances', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment', 'user_id' => 1]);
  $plan = InstallmentPlan::factory()->create(['payment_id' => $payment->id, 'status' => 'active']);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $fromWallet = Mockery::mock(\App\Models\Wallet::class)->makePartial();
  $fromWallet->id = 10;
  $fromWallet->balance = 1000.0;
  $fromWallet->shouldReceive('hasSufficientBalance')->andReturn(true);
  $this->repository->shouldReceive('findWalletByUserId')->with(1)->andReturn($fromWallet);

  $toWallet = \App\Models\Wallet::factory()->create(['id' => 20]);
  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD', toWallet: $toWallet);

  $this->repository->shouldReceive('debitWallet')->once()->with($fromWallet, Mockery::any());
  $this->repository->shouldReceive('creditWallet')->once()->with($toWallet, Mockery::any());

  // الحل: إرجاع موديل Transaction حقيقي باستخدام الـ Factory لتجاوز الـ TypeError
  $transaction = \App\Models\Transaction::factory()->make(['id' => 777]);
  $this->repository->shouldReceive('createWalletTransaction')->once()->andReturn($transaction);

  $updatedPlan = InstallmentPlan::factory()->make(['status' => 'active']);
  $this->repository->shouldReceive('incrementPaidInstallments')->once()->andReturn($updatedPlan);
  $this->repository->shouldNotReceive('markPlanCompleted');

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['payment_method'])->toBe('wallet')
    ->and($result['transaction_id'])->toBe(777);
});

// 4. اختبار: نجاح الدفع وإغلاق الخطة بالكامل عند سداد آخر قسط عبر المحفظة
test('it completes the plan when paid via wallet and installments are finished', function () {
  $payment = Payment::factory()->create(['payment_type' => 'installment']);
  $plan = InstallmentPlan::factory()->create(['payment_id' => $payment->id, 'status' => 'active']);
  $payment->setRelation('installmentPlan', $plan);

  $this->repository->shouldReceive('findPayment')->andReturn($payment);

  $fromWallet = Mockery::mock(\App\Models\Wallet::class)->makePartial();
  $fromWallet->shouldReceive('hasSufficientBalance')->andReturn(true);
  $this->repository->shouldReceive('findWalletByUserId')->andReturn($fromWallet);

  $toWallet = \App\Models\Wallet::factory()->create(['id' => 20]);
  $dto = new PayInstallmentDTO($payment->id, 'wallet', 'USD', toWallet: $toWallet);

  // الحل هنا أيضاً: استخدام الـ Factory لبناء كائن الـ Transaction
  $transaction = \App\Models\Transaction::factory()->make(['id' => 888]);
  $this->repository->shouldReceive('createWalletTransaction')->andReturn($transaction);

  $completedPlan = Mockery::mock(InstallmentPlan::class)->makePartial()->shouldIgnoreMissing();
  $completedPlan->shouldReceive('isCompleted')->andReturn(true);
  $completedPlan->shouldReceive('remainingInstallments')->andReturn(0);
  $completedPlan->status = 'completed';

  $this->repository->shouldReceive('incrementPaidInstallments')->andReturn($completedPlan);

  $this->repository->shouldReceive('markPlanCompleted')->once()->with($completedPlan);
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PAID);

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['plan_status'])->toBe('completed');
});
