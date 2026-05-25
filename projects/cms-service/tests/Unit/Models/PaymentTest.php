<?php

use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it calculates remaining amount correctly', function () {
  $payment = Payment::factory()->create(['amount' => 1000]);

  // إنشاء حركة استرداد (Refund) بقيمة 200
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_REFUND,
    'status' => Transaction::STATUS_SUCCESS,
    'amount' => 200,
  ]);

  expect($payment->refundedAmount())->toBe(200.0)
    ->and($payment->remainingAmount())->toBe(800.0);
});

test('it identifies payment status correctly', function () {
  $payment = Payment::factory()->create(['status' => Payment::STATUS_PAID]);

  expect($payment->isPaid())->toBeTrue();
});

test('it identifies installment payment type', function () {
  $payment = Payment::factory()->create(['payment_type' => Payment::TYPE_INSTALLMENT]);

  expect($payment->isInstallment())->toBeTrue();
});

test('it identifies if payment is paid in full', function () {
  $payment = Payment::factory()->create(['amount' => 500]);

  // دفع مبلغ جزئي
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_CHARGE,
    'status' => Transaction::STATUS_SUCCESS,
    'amount' => 200,
  ]);
  expect($payment->isPaidInFull())->toBeFalse();

  // إكمال المبلغ
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_CHARGE,
    'status' => Transaction::STATUS_SUCCESS,
    'amount' => 300,
  ]);
  expect($payment->isPaidInFull())->toBeTrue();
});

test('it retrieves latest transaction', function () {
  $payment = Payment::factory()->create();

  // إنشاء حركتين
  $t1 = Transaction::factory()->create(['payment_id' => $payment->id, 'created_at' => now()->subDay()]);
  $t2 = Transaction::factory()->create(['payment_id' => $payment->id, 'created_at' => now()]);

  expect($payment->latestTransaction()->id)->toBe($t2->id);
});

test('it has relationships', function () {
  $project = Project::factory()->create();
  $payment = Payment::factory()->create(['project_id' => $project->id]);

  // اختبار العلاقة مع المشروع
  expect($payment->project)->toBeInstanceOf(Project::class)
    ->and($payment->project->id)->toBe($project->id);

  // اختبار العلاقة مع المعاملات
  expect($payment->transactions)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);

  // اختبار العلاقة مع خطة التقسيط (اختياري، يتطلب وجود الموديل)
  $plan = InstallmentPlan::factory()->create(['payment_id' => $payment->id]);
  expect($payment->installmentPlan)->toBeInstanceOf(InstallmentPlan::class);
});
