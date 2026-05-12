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
    Schema::create('subscription_usages', function (Blueprint $table) {

      $table->id();

      $table->foreignId('subscription_id')
        ->constrained()
        ->cascadeOnDelete();

      $table->string('feature_key');

      $table->unsignedBigInteger('used_value')
        ->default(0);

      $table->timestamp('reset_at')
        ->nullable();

      $table->timestamps();

      $table->unique([
        'subscription_id',
        'feature_key'
      ]);

      $table->index([
        'feature_key'
      ]);

      $table->index([
        'reset_at'
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
