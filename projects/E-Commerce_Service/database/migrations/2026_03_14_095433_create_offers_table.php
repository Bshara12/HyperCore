<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('offers', function (Blueprint $table) {
      $table->id();

      $table->unsignedInteger('project_id');
      $table->unsignedInteger('collection_id');

      $table->boolean('is_code_offer')->default(false);
      $table->unsignedInteger('offer_duration')->nullable();
      $table->string('code')->nullable();

      $table->string('benefit_type');
      $table->json('benefit_config')->nullable();

      $table->timestamp('start_at')->nullable();
      $table->timestamp('end_at')->nullable();

      $table->boolean('is_active')->default(true);

      $table->timestamps();
      $table->softDeletes();

      // Single indexes
      $table->index('project_id');
      $table->index('collection_id');
      $table->index('is_active');
      $table->index('is_code_offer');
      $table->index('benefit_type');
      $table->index('start_at');
      $table->index('end_at');
      $table->index('code');
      $table->index('deleted_at');

      // Composite indexes
      $table->index(['project_id', 'deleted_at']);
      $table->index(['project_id', 'collection_id']);
      $table->index(['project_id', 'is_active']);
      $table->index(['project_id', 'benefit_type']);
      $table->index(['is_code_offer', 'code']);
      $table->index(['is_code_offer', 'offer_duration']);
      $table->index(['project_id', 'is_active', 'deleted_at']);
      $table->index(['project_id', 'collection_id', 'deleted_at']);
      $table->index(['project_id', 'benefit_type', 'deleted_at']);
      $table->index(['project_id', 'collection_id', 'is_active', 'deleted_at']);
      $table->index(['project_id', 'start_at', 'deleted_at']);
      $table->index(['project_id', 'end_at', 'deleted_at']);
      $table->index(['project_id', 'collection_id', 'is_active', 'start_at', 'end_at']);

      $table->unique(['project_id', 'code']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('offers');
  }
};
