<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('booking_cancellation_policies', function (Blueprint $table) {
      $table->id();

      $table->foreignId('resource_id')
        ->constrained('resources')
        ->cascadeOnDelete();

      $table->unsignedInteger('hours_before');
      $table->unsignedTinyInteger('refund_percentage');
      $table->string('description')->nullable();

      $table->timestamps();
      $table->index(['resource_id', 'hours_before']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('booking_cancellation_policies');
  }
};
