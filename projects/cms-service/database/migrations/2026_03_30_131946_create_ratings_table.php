<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('ratings', function (Blueprint $table) {
      $table->id();

      // user (optional للـ guest)
      $table->foreignId('user_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

      // polymorphic relation
      $table->string('rateable_type');
      $table->unsignedBigInteger('rateable_id');

      // rating
      $table->tinyInteger('rating'); // 1 → 5
      $table->text('review')->nullable();

      $table->timestamps();

      // منع التكرار
      $table->unique(
        ['user_id', 'rateable_type', 'rateable_id'],
        'unique_user_rating'
      );

      // تحسين الأداء
      $table->index(
        ['rateable_type', 'rateable_id'],
        'rateable_index'
      );
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('ratings');
  }
};
