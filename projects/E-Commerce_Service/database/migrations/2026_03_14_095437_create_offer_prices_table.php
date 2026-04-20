<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('offer_prices', function (Blueprint $table) {
      $table->id();

      $table->unsignedInteger('entry_id');

      $table->foreignId('applied_offer_id')
        ->constrained('offers')
        ->nullOnDelete()
        ->cascadeOnUpdate()
        ->cascadeOnDelete();

      $table->decimal('original_price', 14, 2);
      $table->decimal('final_price', 14, 2);

      $table->boolean('is_applied')->default(false);
      $table->boolean('is_code_price')->default(false);

      // $table->timestamp('valid_until')->nullable();

      $table->timestamps();

      $table->unique(['entry_id', 'applied_offer_id']);
      $table->index('entry_id');
      $table->index('applied_offer_id');
      $table->index('final_price');
      $table->index('is_applied');
      $table->index(['entry_id', 'is_applied', 'is_code_price']);
      $table->index(['entry_id', 'is_applied', 'original_price']);
      $table->index(['entry_id', 'is_applied', 'final_price']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('applied_prices');
  }
};
