<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('data_entry_values', function (Blueprint $table) {

      // إضافة index عادي بنفس الأعمدة
      $table->index(
        ['data_entry_id', 'data_type_field_id', 'language'],
        'dev_entry_field_lang_index'
      );

      // (اختياري لكنه ممتاز للأداء)
      $table->index('data_entry_id', 'dev_entry_id_index');
    });
  }

  public function down(): void
  {
    Schema::table('data_entry_values', function (Blueprint $table) {

      $table->dropIndex('dev_entry_field_lang_index');
      $table->dropIndex('dev_entry_id_index');

      $table->unique(
        ['data_entry_id', 'data_type_field_id', 'language'],
        'dev_entry_field_lang_unique'
      );
    });
  }
};
