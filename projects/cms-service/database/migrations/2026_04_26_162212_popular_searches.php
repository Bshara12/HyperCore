<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * لماذا جدول منفصل وليس query مباشرة على user_search_logs؟
         *
         * user_search_logs يكبر بسرعة (ملايين سجلات)
         * الـ GROUP BY + ORDER BY على جدول كبير بطيء جداً
         * هذا الجدول = materialized view يُحدَّث بشكل دوري
         * الـ read = O(1) مع index بدل O(n) scan
         */
        Schema::create('popular_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('keyword', 200);
            $table->string('language', 10)->default('en');
            $table->string('normalized_keyword', 200);

            // ─── إحصائيات النوافذ الزمنية ─────────────────────────────
            $table->unsignedInteger('count_24h')->default(0);
            $table->unsignedInteger('count_7d')->default(0);
            $table->unsignedInteger('count_30d')->default(0);
            $table->unsignedInteger('count_all_time')->default(0);

            // ─── للـ trending calculation ─────────────────────────────
            $table->unsignedInteger('click_count')->default(0);
            $table->decimal('trending_score', 10, 4)->default(0);
            $table->decimal('alltime_score', 10, 4)->default(0);

            $table->timestamp('last_searched_at')->nullable();
            $table->timestamp('last_computed_at')->nullable(); // متى آخر حساب

            $table->timestamps();

            $table->unique(
                ['project_id', 'normalized_keyword', 'language'],
                'ps_project_keyword_lang_unique'
            );

            // Index لجلب الـ trending
            $table->index(
                ['project_id', 'language', 'trending_score'],
                'ps_trending_idx'
            );

            // Index لجلب الـ all-time
            $table->index(
                ['project_id', 'language', 'alltime_score'],
                'ps_alltime_idx'
            );

            // Index للنوافذ الزمنية
            $table->index(
                ['project_id', 'language', 'count_24h'],
                'ps_24h_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_searches');
    }
};