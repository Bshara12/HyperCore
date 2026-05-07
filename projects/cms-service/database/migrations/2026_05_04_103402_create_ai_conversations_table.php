<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('ai_conversations', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('user_id')->index();
      $table->string('title')->nullable();
      $table->unsignedBigInteger('provisioned_project_id')->nullable();
      $table->enum('status', ['active', 'archived'])
        ->default('active')
        ->index();

      $table->timestamps();
      $table->softDeletes();

      $table->index(['user_id', 'status']);
      $table->index(['user_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('ai_conversations');
  }
};
