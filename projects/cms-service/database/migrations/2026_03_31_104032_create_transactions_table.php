<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('transactions', function (Blueprint $table) {
      $table->id();

      $table->foreignId('payment_id')
        ->nullable()
        ->constrained('payments')
        ->cascadeOnDelete();

      // ─── نوع العملية ───────────────────────────────────────────────
      $table->enum('type', ['charge', 'refund'])->index();

      // ─── طريقة الدفع ───────────────────────────────────────────────
      $table->enum('payment_method', ['gateway', 'wallet'])->index();

      // ─── بيانات الـ Gateway (nullable لأن wallet لا يحتاجها) ───────
      $table->string('gateway_transaction_id')->nullable()->index();
      $table->json('gateway_response')->nullable();

      // ─── بيانات المحفظة (nullable لأن gateway لا يحتاجها) ──────────
      $table->foreignId('from_wallet_id')
        ->nullable()
        ->constrained('wallets')
        ->nullOnDelete();

      $table->foreignId('to_wallet_id')
        ->nullable()
        ->constrained('wallets')
        ->nullOnDelete();

      // ─── بيانات التقسيط ────────────────────────────────────────────
      // null = دفع كامل، 0 = down payment، 1,2,3... = دفعات
      $table->unsignedInteger('installment_number')
        ->nullable()->index();

      // ─── المبلغ والحالة ────────────────────────────────────────────
      $table->decimal('amount', 12, 2);
      $table->char('currency', 3)->default('USD');

      $table->enum('status', ['pending', 'success', 'failed'])
        ->default('pending')->index();

      $table->timestamp('processed_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('transactions');
  }
};
