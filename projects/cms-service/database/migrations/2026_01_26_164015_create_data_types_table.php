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
    Schema::create('data_types', function (Blueprint $table) {
      $table->id();
      $table->foreignId('project_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('slug');
      $table->string('description')->nullable();
      $table->boolean('is_active')->default(true);
      $table->json('settings')->nullable();
      $table->softDeletes();
      $table->timestamps();
      $table->unique(['project_id', 'slug']);
      $table->index('project_id');
      $table->index('is_active');
      $table->index('slug');
      $table->index(['project_id', 'slug']);
      $table->index(['project_id', 'is_active']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_types');
  }
};
