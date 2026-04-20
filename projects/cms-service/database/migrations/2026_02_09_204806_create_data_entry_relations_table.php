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
    Schema::create('data_entry_relations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('data_entry_id')->constrained('data_entries')->cascadeOnDelete();
      $table->foreignId('related_entry_id')->constrained('data_entries')->cascadeOnDelete();
      $table->foreignId('data_type_relation_id')->constrained('data_type_relations')->cascadeOnDelete();
      $table->timestamps();
      $table->index(['data_entry_id', 'related_entry_id', 'data_type_relation_id'], 'der_entry_related_relation_idx');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_entry_relations');
  }
};
