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
    Schema::create('content_access_metadata', function (Blueprint $table) {

      $table->id();

      $table->foreignId('project_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

      /*
    |--------------------------------------------------------------------------
    | Polymorphic Content
    |--------------------------------------------------------------------------
    */

      $table->string('content_type');

      $table->unsignedBigInteger('content_id');

      /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

      $table->boolean('requires_subscription')
        ->default(false);

      $table->string('required_feature')
        ->nullable();

      /*
    |--------------------------------------------------------------------------
    | Optional
    |--------------------------------------------------------------------------
    */

      $table->json('metadata')
        ->nullable();

      $table->timestamps();

      $table->boolean('is_active')
        ->default(true);

      $table->index([
        'content_type',
        'content_id'
      ]);
      $table->unique([
        'project_id',
        'content_type',
        'content_id'
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
