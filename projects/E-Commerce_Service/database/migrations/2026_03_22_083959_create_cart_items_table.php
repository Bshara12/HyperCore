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
    Schema::create('cart_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('cart_id')
        ->constrained('carts')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->unsignedInteger('item_id');
      $table->integer('quantity');
      $table->timestamps();

      $table->index('cart_id');
      $table->index('item_id');
      $table->index(['cart_id', 'item_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cart_items');
  }
};
