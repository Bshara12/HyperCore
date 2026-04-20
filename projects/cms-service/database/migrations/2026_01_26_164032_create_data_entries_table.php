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
    Schema::create('data_entries', function (Blueprint $table) {
      $table->id();
      $table->string('slug');
      $table->foreignId('data_type_id')->constrained()->cascadeOnDelete();
      $table->foreignId('project_id')->constrained()->cascadeOnDelete();
      $table->unique(['project_id', 'slug']);
      $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])
        ->default('draft');

      $table->timestamp('scheduled_at')->nullable();

      $table->foreignId('created_by')->nullable()->constrained('users');


      // ⭐ ratings (إضافة جديدة)
      $table->unsignedInteger('ratings_count')->default(0);
      $table->decimal('ratings_avg', 3, 2)->default(0);

      $table->softDeletes();
      $table->timestamp('published_at')->nullable();
      $table->foreignId('updated_by')->nullable()->constrained('users');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_entries');
  }
};
