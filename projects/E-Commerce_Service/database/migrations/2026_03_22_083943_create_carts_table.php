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
    Schema::create('carts', function (Blueprint $table) {
      $table->id();
      $table->unsignedInteger('project_id');
      $table->unsignedInteger('user_id');
      $table->timestamps();

      $table->unique(['project_id', 'user_id']);
      $table->index('project_id');
      $table->index('user_id');
      $table->index(['project_id', 'user_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('carts');
  }
};
