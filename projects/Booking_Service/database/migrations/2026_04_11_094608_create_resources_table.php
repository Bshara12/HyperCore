<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('resources', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('data_entry_id')->index();
      $table->unsignedBigInteger('project_id')->index();

      $table->string('name');
      $table->string('type'); // room | court | seat | doctor...
      $table->unsignedInteger('capacity')->default(1);
      $table->enum('status', ['active', 'inactive'])->default('active')->index();
      $table->json('settings')->nullable(); // إعدادات مرنة إضافية

      $table->enum('payment_type', ['free', 'paid'])->default('free')->index();
      $table->decimal('price', 12, 2)->nullable();

      $table->softDeletes();
      $table->timestamps();

      $table->index(['project_id', 'status']);
      $table->index(['project_id', 'type']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('resources');
  }
};
