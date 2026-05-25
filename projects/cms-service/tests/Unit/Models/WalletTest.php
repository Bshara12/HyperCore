<?php

use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it correctly checks for sufficient balance', function () {
  $wallet = Wallet::factory()->create(['balance' => 100.00]);

  expect($wallet->hasSufficientBalance(50.00))->toBeTrue()
    ->and($wallet->hasSufficientBalance(150.00))->toBeFalse();
});

test('it has transactions relationships', function () {
  $wallet = Wallet::factory()->create();

  // اختبار العلاقات (افتراض وجود Factory للـ Transaction)
  // التلميح: تأكد من وجود TransactionFactory يربط by from_wallet_id و to_wallet_id
  expect($wallet->sentTransactions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
    ->and($wallet->receivedTransactions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
