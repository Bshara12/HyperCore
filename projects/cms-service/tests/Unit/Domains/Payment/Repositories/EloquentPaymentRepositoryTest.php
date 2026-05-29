<?php

namespace Tests\Unit\Domains\Payment\Repositories;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Repositories\EloquentPaymentRepository;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentPaymentRepository();
});

// ─── Payment Tests ──────────────────────────────────────────────────────────

test('createPayment creates a pending payment record', function () {
  // 1. Arrange: إنشاء المستخدم والمشروع أولاً
  $user = \App\Models\User::factory()->create();
  $project = \App\Models\Project::factory()->create();

  // 2. استخدم ID الخاص بهم في الـ DTO
  $dto = new PaymentDTO(
    userId: $user->id,
    userName: $user->name,
    projectId: $project->id, // استخدم الـ ID الحقيقي
    amount: 100.0,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'full'
  );

  $payment = $this->repository->createPayment($dto);

  // 3. Assert
  $this->assertDatabaseHas('payments', [
    'id' => $payment->id,
    'status' => \App\Models\Payment::STATUS_PENDING,
    'amount' => 100.0
  ]);
});

// ─── Installment Tests ──────────────────────────────────────────────────────

test('createInstallmentPlan creates a plan for a payment', function () {
  $payment = Payment::factory()->create();
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'Test',
    projectId: 1,
    amount: 100.0,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'installment',
    installmentAmount: 50.0,
    totalInstallments: 2
  );

  $plan = $this->repository->createInstallmentPlan($payment, $dto);

  expect($plan->payment_id)->toBe($payment->id)
    ->and($plan->total_installments)->toBe(2)
    ->and($plan->paid_installments)->toBe(0);
});

// ─── Wallet Tests ──────────────────────────────────────────────────────────

test('debitWallet reduces balance correctly', function () {
  $wallet = Wallet::factory()->create(['balance' => 500.0]);

  $this->repository->debitWallet($wallet, 100.0);

  $wallet->refresh();
  expect($wallet->balance)->toBe(400.0);
});

test('creditWallet increases balance correctly', function () {
  $wallet = Wallet::factory()->create(['balance' => 500.0]);

  $this->repository->creditWallet($wallet, 200.0);

  $wallet->refresh();
  expect($wallet->balance)->toBe(700.0);
});

// ─── Transaction Tests ─────────────────────────────────────────────────────

test('createGatewayTransaction creates transaction record', function () {
  $payment = Payment::factory()->create();

  $transaction = $this->repository->createGatewayTransaction(
    $payment,
    'charge',
    'txn_123',
    100.0,
    'USD',
    'success',
    ['code' => 200],
    1
  );

  $this->assertDatabaseHas('transactions', [
    'payment_id' => $payment->id,
    'gateway_transaction_id' => 'txn_123',
    'amount' => 100.0
  ]);
});

// ─── Payment Methods ────────────────────────────────────────────────────────

test('findPayment returns payment with relations loaded', function () {
  $payment = Payment::factory()->hasInstallmentPlan()->create();
  // لنقم بإنشاء Transaction مرتبطة
  $transaction = Transaction::factory()->create(['payment_id' => $payment->id]);

  $result = $this->repository->findPayment($payment->id);

  expect($result)->not->toBeNull()
    ->and($result->relationLoaded('installmentPlan'))->toBeTrue()
    ->and($result->relationLoaded('transactions'))->toBeTrue();
});

test('updatePaymentStatus changes status in database', function () {
  $payment = Payment::factory()->create(['status' => 'pending']);

  // استبدل 'completed' بـ 'paid'
  $updatedPayment = $this->repository->updatePaymentStatus($payment, 'paid');

  expect($updatedPayment->status)->toBe('paid');
});
// ─── Installment Plan Methods ───────────────────────────────────────────────

test('incrementPaidInstallments increments count and updates date', function () {
  $plan = InstallmentPlan::factory()->create([
    'paid_installments' => 0,
    'interval_days' => 30
  ]);

  $updatedPlan = $this->repository->incrementPaidInstallments($plan);

  expect($updatedPlan->paid_installments)->toBe(1)
    ->and($updatedPlan->next_due_date->format('Y-m-d'))->toBe(now()->addDays(30)->format('Y-m-d'));
});

test('markPlanCompleted updates status and nullifies due date', function () {
  $plan = InstallmentPlan::factory()->create(['status' => 'active']);

  $updatedPlan = $this->repository->markPlanCompleted($plan);

  expect($updatedPlan->status)->toBe(InstallmentPlan::STATUS_COMPLETED)
    ->and($updatedPlan->next_due_date)->toBeNull();
});

// ─── Wallet & Transaction Methods ──────────────────────────────────────────

test('createWalletTransaction saves data correctly', function () {
  // 1. Arrange: إنشاء البيانات المطلوبة أولاً
  $payment = Payment::factory()->create();
  $fromWallet = Wallet::factory()->create(); // إنشاء محفظة المصدر
  $toWallet = Wallet::factory()->create();   // إنشاء محفظة الوجهة

  // 2. Act: استخدام الـ IDs الحقيقية الناتجة من الإنشاء
  $transaction = $this->repository->createWalletTransaction(
    $payment,
    \App\Models\Transaction::TYPE_CHARGE, // التأكد من استخدام الثابت الصحيح
    $fromWallet->id,
    $toWallet->id,
    50.0,
    'USD',
    \App\Models\Transaction::STATUS_SUCCESS,
    1
  );

  // 3. Assert: التأكد من الحفظ
  $this->assertDatabaseHas('transactions', [
    'payment_id' => $payment->id,
    'from_wallet_id' => $fromWallet->id,
    'to_wallet_id' => $toWallet->id,
    'amount' => 50.0
  ]);
});

test('findWalletByUserId returns correct wallet', function () {
  $user = \App\Models\User::factory()->create();
  $wallet = Wallet::factory()->create(['user_id' => $user->id]);

  $foundWallet = $this->repository->findWalletByUserId($user->id);

  expect($foundWallet->id)->toBe($wallet->id);
});


// أضف هذه الاختبارات تحت قسم // ─── Wallet Tests ──────────────────────────────────────────────────────────

test('findWalletByNumber returns correct wallet when number exists', function () {
  // 1. Arrange: إنشاء محفظة برقم معين
  $wallet = Wallet::factory()->create(['wallet_number' => 'W-99999']);

  // 2. Act: البحث عن المحفظة باستخدام الرقم
  $foundWallet = $this->repository->findWalletByNumber('W-99999');

  // 3. Assert: التأكد من مطابقة المحفظة المسترجعة
  expect($foundWallet)->not->toBeNull()
    ->and($foundWallet->id)->toBe($wallet->id)
    ->and($foundWallet->wallet_number)->toBe('W-99999');
});

test('findWalletByNumber returns null when number does not exist', function () {
  // 1. Act: البحث عن رقم محفظة غير موجود في قاعدة البيانات
  $foundWallet = $this->repository->findWalletByNumber('NOT-FOUND');

  // 2. Assert: التأكد من إرجاع قيمة null
  expect($foundWallet)->toBeNull();
});
