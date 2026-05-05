<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_indices', function (Blueprint $table) {

            // ─── Precomputed Columns ──────────────────────────────────
            // تُحسب مرة واحدة عند الفهرسة وتُخزَّن
            // بدل حسابها في كل query

            // هل العنوان يحتوي أرقاماً؟ (مهم للـ model numbers)
            $table->boolean('title_has_numbers')
                ->default(false)
                ->after('published_at');

            // عدد كلمات العنوان (للـ specificity scoring)
            $table->unsignedTinyInteger('title_word_count')
                ->default(0)
                ->after('title_has_numbers');

            // طول العنوان (للـ normalization)
            $table->unsignedSmallInteger('title_length')
                ->default(0)
                ->after('title_word_count');

            // الكلمة الأساسية المستخرجة من العنوان (للـ fast matching)
            $table->string('primary_keyword', 100)
                ->nullable()
                ->after('title_length');

            // CTR score مُحسوب مسبقاً (يُحدَّث بالـ Job)
            $table->decimal('ctr_score', 8, 4)
                ->default(0)
                ->after('primary_keyword');

            // Freshness score مُحسوب (يُحدَّث يومياً)
            $table->decimal('freshness_score', 8, 4)
                ->default(0)
                ->after('ctr_score');

            // data_type_id للـ intent filtering بدون JOIN
            // هذا denormalization مقصود للأداء
            $table->string('data_type_slug', 100)
                ->nullable()
                ->after('freshness_score');
        });

        // ─── Critical Composite Index ─────────────────────────────────
        // هذا الـ index الأهم: يُغطي الـ WHERE clause الشائع
        // (project_id, language, status, data_type_id)
        // بدلاً من 4 indexes منفصلة
        DB::statement("
            ALTER TABLE search_indices
            ADD INDEX si_filter_composite_idx
            (project_id, language, status, data_type_id, published_at)
        ");

        // ─── Data Type Slug Index (للـ intent filtering بدون JOIN) ────
        DB::statement("
            ALTER TABLE search_indices
            ADD INDEX si_data_type_slug_idx
            (project_id, language, data_type_slug)
        ");

        // ─── CTR + Freshness Index (للـ ranking المُحسوب) ─────────────
        DB::statement("
            ALTER TABLE search_indices
            ADD INDEX si_ranking_signals_idx
            (project_id, language, status, ctr_score, freshness_score)
        ");

        // ─── Primary Keyword Index (للـ fast title matching) ──────────
        DB::statement("
            ALTER TABLE search_indices
            ADD INDEX si_primary_keyword_idx
            (project_id, primary_keyword)
        ");

        // ─── تحديث البيانات الموجودة ──────────────────────────────────
        DB::statement("
            UPDATE search_indices
            SET
                title_has_numbers = (title REGEXP '[0-9]'),
                title_word_count  = (
                    CHAR_LENGTH(TRIM(title))
                    - CHAR_LENGTH(REPLACE(TRIM(title), ' ', ''))
                    + 1
                ),
                title_length      = CHAR_LENGTH(COALESCE(title, '')),
                primary_keyword   = LOWER(SUBSTRING_INDEX(TRIM(title), ' ', 1)),
                freshness_score   = ROUND(
                    1.0 / (DATEDIFF(NOW(), COALESCE(published_at, NOW())) + 1),
                    4
                )
        ");
    }

    public function down(): void
    {
        Schema::table('search_indices', function (Blueprint $table) {
            $table->dropIndex('si_filter_composite_idx');
            $table->dropIndex('si_data_type_slug_idx');
            $table->dropIndex('si_ranking_signals_idx');
            $table->dropIndex('si_primary_keyword_idx');

            $table->dropColumn([
                'title_has_numbers',
                'title_word_count',
                'title_length',
                'primary_keyword',
                'ctr_score',
                'freshness_score',
                'data_type_slug',
            ]);
        });
    }
};