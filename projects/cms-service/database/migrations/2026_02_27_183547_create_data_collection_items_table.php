<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up()
  {
    Schema::create('data_collection_items', function (Blueprint $table) {
      $table->id();

      $table->foreignId('collection_id')
        ->constrained('data_collections')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->foreignId('item_id')
        ->constrained('data_entries')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->integer('sort_order')->default(0);

      $table->timestamps();
      $table->index('collection_id');
      $table->index('item_id');
      $table->index('sort_order');
    });
  }


  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_collection_items');
  }
};
