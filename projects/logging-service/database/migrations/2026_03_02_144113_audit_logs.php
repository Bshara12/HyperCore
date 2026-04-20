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
    //
    Schema::create('audit_logs', function (Blueprint $table) {
      $table->id();
      $table->uuid('event_id')->unique();
      $table->string('module');
      $table->string('entity_type');
      $table->unsignedBigInteger('entity_id');
      $table->json('old_values')->nullable();
      $table->json('new_values')->nullable();
      $table->unsignedBigInteger('user_id')->nullable();
      $table->string('correlation_id')->nullable();
      $table->timestamp('occurred_at');
      $table->timestamps();
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
