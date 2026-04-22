<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('bookings', function (Blueprint $table) {
      $table->id();

      $table->foreignId('resource_id')
        ->constrained('resources')
        ->cascadeOnDelete();

      $table->unsignedBigInteger('user_id')->index();
      $table->unsignedBigInteger('project_id')->index();

      // payment_id في CMS — nullable لأن الحجز يُنشأ أولاً ثم يُدفع
      $table->unsignedBigInteger('payment_id')->nullable()->index();

      $table->dateTime('start_at')->index();
      $table->dateTime('end_at')->index();

      $table->enum('status', [
        'pending',    // بانتظار الدفع
        'confirmed',  // مؤكد بعد الدفع
        'cancelled',  // ملغى
        'completed',  // انتهى
        'no_show',    // لم يحضر
      ])->default('pending')->index();

      $table->decimal('amount', 12, 2);
      $table->char('currency', 3)->default('USD');

      $table->text('notes')->nullable();
      $table->text('cancellation_reason')->nullable();  // سبب الإلغاء
      $table->decimal('refund_amount', 12, 2)->nullable(); // المبلغ المسترد

      $table->softDeletes();
      $table->timestamps();

      $table->index(['resource_id', 'start_at', 'end_at']);
      $table->index(['resource_id', 'status']);
      $table->index(['user_id', 'status']);
      $table->index(['project_id', 'status']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('bookings');
  }
};
