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
    Schema::create('data_entry_versions', function (Blueprint $table) {
      $table->id();
      $table->foreignId('data_entry_id')->constrained()->cascadeOnDelete();
      $table->integer('version_number');
      $table->json('snapshot');
      $table->foreignId('created_by')->nullable()->constrained('users');
      $table->unique(
        ['data_entry_id', 'version_number'],
        'dev_entry_version_unique'
      );
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('data_entry_versions');
  }
};
