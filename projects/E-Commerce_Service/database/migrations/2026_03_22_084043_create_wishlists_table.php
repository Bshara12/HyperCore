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
    Schema::create('wishlists', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->string('guest_token')->nullable()->index();
        $table->string('name');
        $table->boolean('is_default')->default(false);
        $table->enum('visibility', ['private', 'public'])->default('private');
        $table->string('share_token')->nullable()->unique();
        $table->boolean('is_shareable')->default(false);
        $table->timestamps();
        $table->unique(['user_id', 'name']);
        $table->unique(['guest_token', 'name']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('wishlists');
  }
};
