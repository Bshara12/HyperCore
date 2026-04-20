<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('payments', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id')->index();
      $table->foreignId('project_id')
        ->constrained('projects')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->string('gateway');
      $table->enum('payment_type', ['full', 'installment'])
        ->default('full')->index();

      $table->decimal('amount', 12, 2);
      $table->char('currency', 3)->default('USD');

      $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])
        ->default('pending')->index();

      $table->string('description')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('payments');
  }
};
