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
    Schema::create('data_entry_values', function (Blueprint $table) {
      $table->id();
      $table->foreignId('data_entry_id')->constrained()->cascadeOnDelete();
      $table->foreignId('data_type_field_id')->constrained()->cascadeOnDelete();
      $table->string('language')->nullable();
      $table->longText('value')->nullable();
      $table->softDeletes();
      $table->timestamps();

      // $table->index(['data_entry_id', 'data_type_field_id', 'language']);
      // $table->unique(
      //   ['data_entry_id', 'data_type_field_id', 'language'],
      //   'dev_entry_field_lang_unique'
      // );
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_entry_values');
  }
};
