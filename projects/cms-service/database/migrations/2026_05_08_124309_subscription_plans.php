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
    Schema::create('subscription_plans', function (Blueprint $table) {

      $table->id();

      $table->foreignId('project_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

      $table->string('name');

      $table->string('slug');

      $table->text('description')->nullable();

      $table->decimal('price', 12, 2);

      $table->string('currency', 3)
        ->default('USD');

      $table->unsignedInteger('duration_days');

      $table->boolean('is_active')
        ->default(true)
        ->index();

      $table->json('metadata')
        ->nullable();

      $table->timestamps();

      $table->unique([
        'project_id',
        'slug'
      ]);

      $table->index([
        'project_id',
        'is_active'
      ]);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    //
  }
};
