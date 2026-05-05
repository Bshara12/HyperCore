<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_indices', function (Blueprint $table) {
            $table->id();

            // ─── المصدر ────────────────────────────────────────────────
            $table->unsignedBigInteger('entry_id')->index();
            $table->unsignedBigInteger('data_type_id')->index();
            $table->unsignedBigInteger('project_id')->index();

            // ─── بيانات الفهرسة ─────────────────────────────────────────
            $table->string('language', 10)->default('en')->index();
            $table->string('title')->nullable();          // أعلى وزن
            $table->longText('content')->nullable();      // وزن متوسط
            $table->longText('meta')->nullable();         // أدنى وزن (JSON)

            // ─── بيانات مساعدة ──────────────────────────────────────────
            $table->string('status', 20)->default('published')->index();
            $table->timestamp('published_at')->nullable()->index();

            // ─── Full-Text Index ─────────────────────────────────────────
            // سيُضاف بعد إنشاء الجدول (MySQL FULLTEXT لا يدعم nullable بسهولة)
            $table->timestamps();

            // ─── Constraints ─────────────────────────────────────────────
            $table->unique(
                ['entry_id', 'language'],
                'search_indices_entry_lang_unique'
            );
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->decimal('popularity_score', 8, 4)->default(0);

            // Index للـ CTR query (click_count / view_count)
            $table->index(['project_id', 'click_count'], 'si_project_clicks_idx');
            $table->index(['project_id', 'popularity_score'], 'si_project_popularity_idx');

            $table->index(['project_id', 'data_type_id', 'language'], 'search_project_type_lang_idx');
            $table->index(['project_id', 'status', 'language'], 'search_project_status_lang_idx');
        });

        // ─── FULLTEXT index يُضاف منفصلاً ───────────────────────────────
        DB::statement('ALTER TABLE search_indices ADD FULLTEXT fulltext_title_content (title, content)');
    }

    public function down(): void
    {
        Schema::table('search_indices', function (Blueprint $table) {
            $table->dropIndex('si_project_clicks_idx');
            $table->dropIndex('si_project_popularity_idx');
            $table->dropColumn(['click_count', 'view_count', 'popularity_score']);
        });
    }
};
