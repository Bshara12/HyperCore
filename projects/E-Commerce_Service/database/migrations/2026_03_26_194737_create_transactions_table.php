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
        ->constrained('payments')
        ->cascadeOnDelete();

      $table->string('gateway_transaction_id')->index();

      $table->enum('type', ['charge', 'refund'])->index();

      $table->decimal('amount', 12, 2);
      $table->char('currency', 3)->default('USD');

      $table->enum('status', [
        'pending',
        'success',
        'failed',
      ])->default('pending')->index();

      $table->json('gateway_response')->nullable();
      $table->timestamp('processed_at')->nullable();

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('transactions');
  }
};
