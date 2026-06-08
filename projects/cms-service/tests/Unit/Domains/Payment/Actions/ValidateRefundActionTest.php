<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\ValidateRefundAction;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->action = new ValidateRefundAction();
});

// 1. اختبار: نجاح التحقق عندما يكون مبلغ الاسترداد أقل من أو يساوي المبلغ المتاح
test('it passes validation when refund amount is within the remaining amount', function () {
  // إنشاء دفعة بقيمة 100 ولم يسترد منها شيء بعد
  $payment = Payment::factory()->create([
    'amount' => 100.00,
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 60.00, // الـ 60 أصغر من الـ 100 المتبقية
    'currency' => 'USD',
  ]);

  // نتوقع أن يمر الكود بسلام دون إطلاق أي استثناء (Exception)
  expect(fn() => $this->action->execute($payment, $dto))
    ->not->toThrow(\Exception::class);
});

// 2. اختبار: نجاح التحقق عند وجود عمليات استرداد سابقة ناجحة ولكن المبلغ الجديد لا يزال ضمن الحدود
test('it passes validation when prior successful refunds exist but total stays within limit', function () {
  // إنشاء دفعة بقيمة 100
  $payment = Payment::factory()->create([
    'amount' => 100.00,
  ]);

  // تسجيل عملية استرداد سابقة ناجحة بقيمة 40
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_REFUND,
    'status' => Transaction::STATUS_SUCCESS,
    'amount' => 40.00,
  ]);

  // طلب استرداد جديد بقيمة 50 (المجموع 40 + 50 = 90، وهي أقل من 100)
  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
  ]);

  expect(fn() => $this->action->execute($payment, $dto))
    ->not->toThrow(\Exception::class);
});

// 3. اختبار: فشل التحقق وإطلاق استثناء عندما يتجاوز مبلغ الاسترداد القيمة المتبقية
test('it throws an exception when the refund amount exceeds the remaining amount', function () {
  // إنشاء دفعة بقيمة 100
  $payment = Payment::factory()->create([
    'amount' => 100.00,
  ]);

  // تسجيل عملية استرداد سابقة ناجحة بقيمة 70 (المتبقي حالياً هو 30 فقط)
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_REFUND,
    'status' => Transaction::STATUS_SUCCESS,
    'amount' => 70.00,
  ]);

  // طلب استرداد جديد بقيمة 40 (المجموع 70 + 40 = 110، وهي أكبر من 100)
  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 40.00,
    'currency' => 'USD',
  ]);

  // نتوقع إطلاق Exception بالرسالة المحددة تماماً في الكود
  expect(fn() => $this->action->execute($payment, $dto))
    ->toThrow(\Exception::class, 'Refund exceeds remaining amount.');
});

// 4. اختبار: التأكد من أن عمليات الاسترداد الفاشلة لا تُحسب ضمن المبلغ المسترد
test('it ignores failed or pending transactions when calculating remaining amount', function () {
  $payment = Payment::factory()->create([
    'amount' => 100.00,
  ]);

  // عملية استرداد فاشلة بقيمة 80 (يجب ألا تؤثر على الحسابات)
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_REFUND,
    'status' => Transaction::STATUS_FAILED, // حالة الفشل
    'amount' => 80.00,
  ]);

  // طلب استرداد جديد بقيمة 50 (بما أن العملية السابقة فشلت، المتبقي لا يزال 100، والـ 50 مسموحة)
  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
  ]);

  expect(fn() => $this->action->execute($payment, $dto))
    ->not->toThrow(\Exception::class);
});

test('it returns the correct circuit service name', function () {
    // استخدام ReflectionClass للوصول إلى الدالة المحمية (protected)
    $reflection = new \ReflectionClass($this->action);
    $method = $reflection->getMethod('circuitServiceName');
    $method->setAccessible(true);

    // استدعاء الدالة والحصول على النتيجة
    $result = $method->invoke($this->action);

    // التأكد من أن القيمة المرجعة مطابقة تماماً
    expect($result)->toBe('Payment-service');
});