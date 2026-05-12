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
    Schema::create('subscription_features', function (Blueprint $table) {

      $table->id();

      $table->foreignId('plan_id')
        ->constrained('subscription_plans')
        ->cascadeOnDelete();

      $table->string('feature_key');

      $table->string('feature_type');

      $table->json('feature_value');

      $table->timestamps();

      $table->unique([
        'plan_id',
        'feature_key'
      ]);

      $table->index([
        'feature_key'
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
