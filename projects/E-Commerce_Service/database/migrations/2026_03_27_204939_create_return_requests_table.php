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
    Schema::create('return_requests', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('order_id');
      $table->unsignedBigInteger('order_item_id');

      $table->text('description')->nullable();
      $table->integer('quantity')->nullable(); // 🔥 مهم لو بده يرجع جزء
      $table->unsignedBigInteger('project_id')->index();
      $table->string('status')->default('pending');
      // pending | approved | rejected

      $table->timestamps();

      $table->index('user_id');
      $table->index('order_id');
      $table->index('order_item_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('return_requests');
  }
};
