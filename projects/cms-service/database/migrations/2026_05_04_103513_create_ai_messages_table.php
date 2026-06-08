<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('ai_messages', function (Blueprint $table) {
      $table->id();

      $table->foreignId('conversation_id')
        ->constrained('ai_conversations')
        ->cascadeOnDelete();

      $table->enum('role', ['user', 'assistant'])->index();
      $table->longText('content');
      $table->json('schema')->nullable();
      $table->boolean('is_provisioned')->default(false);
      $table->unsignedInteger('sequence')->default(1);

      $table->timestamp('created_at')->useCurrent();

      $table->index(['conversation_id', 'sequence']);
      $table->index(['conversation_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('ai_messages');
  }
};
