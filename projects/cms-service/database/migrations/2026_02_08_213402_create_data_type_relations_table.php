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
    Schema::create('data_type_relations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('data_type_id')
        ->constrained('data_types')
        ->cascadeOnDelete();
      $table->foreignId('related_data_type_id')
        ->constrained('data_types')
        ->cascadeOnDelete();
      // one_to_one, one_to_many, many_to_many
      $table->string('relation_type');
      // اسم العلاقة (اختياري)
      $table->string('relation_name')->nullable();
      // pivot table في حالة many_to_many
      $table->string('pivot_table')->nullable();

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_type_relations');
  }
};
