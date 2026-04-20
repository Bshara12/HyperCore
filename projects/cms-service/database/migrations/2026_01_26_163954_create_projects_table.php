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
    Schema::create('projects', function (Blueprint $table) {
      $table->id();
      $table->string('public_id', 36)->unique();
      $table->string('slug')->unique();
      $table->string('name');
      $table->integer('owner_id');
      $table->json('supported_languages')->nullable();
      $table->json('enabled_modules')->nullable();
      // ⭐ ratings (إضافة جديدة)
      $table->unsignedInteger('ratings_count')->default(0);
      $table->decimal('ratings_avg', 3, 2)->default(0);
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('projects');
  }
};
