<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('keyword', 200);
            $table->string('language', 10)->default('en');
            $table->string('normalized_keyword', 200);  // lowercase + trimmed للمقارنة السريعة
            $table->unsignedInteger('search_count')->default(1);  // عدد مرات البحث
            $table->unsignedInteger('click_count')->default(0);   // كم مرة أفضت لنقر
            $table->decimal('score', 8, 4)->default(0);           // score مُحسوب
            $table->timestamp('last_searched_at');
            $table->timestamps();

            // Unique: نفس الـ keyword في نفس project ولغة = سجل واحد
            $table->unique(
                ['project_id', 'normalized_keyword', 'language'],
                'ss_project_keyword_lang_unique'
            );

            // Index الأهم: prefix search + ordering
            $table->index(
                ['project_id', 'language', 'normalized_keyword', 'score'],
                'ss_prefix_search_idx'
            );

            $table->index(
                ['project_id', 'language', 'search_count'],
                'ss_popularity_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_suggestions');
    }
};