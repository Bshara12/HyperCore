<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('installment_plans', function (Blueprint $table) {
      $table->id();

      $table->foreignId('payment_id')
        ->unique()
        ->constrained('payments')
        ->cascadeOnDelete();

      // الدفعة الأولى
      $table->decimal('down_payment', 12, 2)->default(0.00);
      // قيمة كل دفعة
      $table->decimal('installment_amount', 12, 2);
      // عدد الدفعات الكلي
      $table->unsignedInteger('total_installments');
      // عدد المدفوع
      $table->unsignedInteger('paid_installments')->default(0);
      // أيام بين كل دفعة
      $table->unsignedInteger('interval_days')->default(30);
      $table->date('next_due_date')->nullable()->index();

      $table->enum('status', ['active', 'completed', 'defaulted'])
        ->default('active')->index();

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('installment_plans');
  }
};
