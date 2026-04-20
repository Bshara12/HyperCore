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
    Schema::create('seo_entries', function (Blueprint $table) {

      $table->id();
      $table->foreignId('data_entry_id')->constrained()->cascadeOnDelete();
      $table->string('language')->nullable();

      $table->string('meta_title')->nullable();
      $table->text('meta_description')->nullable();
      $table->string('slug')->nullable();
      $table->string('canonical_url')->nullable();
      $table->unique(['data_entry_id', 'language']);
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('seo_entries');
  }
};
