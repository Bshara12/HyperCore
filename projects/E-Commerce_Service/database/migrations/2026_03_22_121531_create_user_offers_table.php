<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('user_offers', function (Blueprint $table) {
      $table->id();
      $table->foreignId('offer_id')
        ->constrained('offers')
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->unsignedInteger('user_id');
      $table->unsignedInteger('project_id');

      $table->timestamp('start_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->timestamp('end_at')->default(DB::raw('CURRENT_TIMESTAMP'));

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('user_offers');
  }
};
