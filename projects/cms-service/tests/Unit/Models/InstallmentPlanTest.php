<?php

use App\Models\InstallmentPlan;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it calculates remaining installments and amounts correctly', function () {
  $plan = InstallmentPlan::factory()->create([
    'total_installments' => 10,
    'paid_installments'  => 3,
    'installment_amount' => 500.0,
  ]);

  expect($plan->remainingInstallments())->toBe(7)
    ->and($plan->remainingAmount())->toBe(3500.0)
    ->and($plan->nextInstallmentNumber())->toBe(4);
});

test('it identifies if plan is completed', function () {
  $plan = InstallmentPlan::factory()->create([
    'total_installments' => 5,
    'paid_installments'  => 5,
  ]);

  expect($plan->isCompleted())->toBeTrue();
});

test('it has relationship with payment', function () {
  $payment = Payment::factory()->create();

  // الحل: توفير جميع الحقول الإجبارية الموجودة في الميغريشن
  $plan = InstallmentPlan::create([
    'payment_id'         => $payment->id,
    'total_installments' => 1,
    'installment_amount' => 100.0, // أضفنا هذا الحقل المفقود
    'paid_installments'  => 0,
    'status'             => InstallmentPlan::STATUS_ACTIVE,
  ]);

  expect($plan->payment)->toBeInstanceOf(Payment::class)
    ->and($plan->payment->id)->toBe($payment->id);
});
