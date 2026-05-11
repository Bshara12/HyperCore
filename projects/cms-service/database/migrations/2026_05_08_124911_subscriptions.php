<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('subscriptions', function (Blueprint $table) {

      $table->id();

      $table->unsignedBigInteger('user_id')
        ->index();

      $table->foreignId('project_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

      $table->foreignId('plan_id')
        ->constrained('subscription_plans')
        ->cascadeOnDelete();

      $table->unsignedBigInteger('payment_id')
        ->nullable()
        ->index();

      $table->enum('status', [
        'pending',
        'active',
        'expired',
        'cancelled',
        'grace_period'
      ])->default('pending');

      $table->timestamp('starts_at');

      $table->timestamp('ends_at');

      $table->timestamp('current_period_start')
        ->nullable();

      $table->timestamp('current_period_end')
        ->nullable();

      $table->timestamp('cancelled_at')
        ->nullable();

      $table->boolean('auto_renew')
        ->default(true);

      $table->json('metadata')
        ->nullable();

      $table->timestamps();

      $table->index([
        'user_id',
        'project_id',
        'status'
      ]);

      $table->index([
        'status',
        'ends_at'
      ]);

      $table->index([
        'project_id',
        'status'
      ]);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    //
  }
};
