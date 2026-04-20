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
    Schema::create('wishlist_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
        $table->unsignedBigInteger('product_id')->index();
        $table->unsignedBigInteger('variant_id')->nullable()->index();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('added_from_cart')->default(false);
        $table->json('product_snapshot')->nullable();
        $table->decimal('price_when_added', 12, 2)->nullable();
        $table->boolean('notify_on_price_drop')->default(false);
        $table->boolean('notify_on_back_in_stock')->default(false);
        $table->unique([
            'wishlist_id',
            'product_id',
            'variant_id'
        ], 'wishlist_product_variant_unique');

        $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('wishlist_items');
  }
};
