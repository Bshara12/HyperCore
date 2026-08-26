<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح البحث العربي — عمود search_text المُطبَّع + FULLTEXT index خاص به
 *
 * السبب الجذري للمشكلة القديمة:
 *   FULLTEXT كان على (title, content) الخامّين. عنوان مثل
 *   "آيفون 15 برو ماكس" يُفهرس كـ token "آيفون"، بينما الـ query
 *   يُطبَّع إلى "ايفون" → لا تطابق أبداً، مهما كان الـ tokenizer.
 *   (والوسوم في meta لم تكن مُفهرسة إطلاقاً.)
 *
 * الحل: عمود search_text يحتوي نصاً مُطبَّعاً بنفس الدالة التي تُطبِّع
 * الـ query (ArabicTextNormalizer) + الوسوم + المصطلحات عبر-اللغوية.
 *
 * الفهرس القديم fulltext_title_content يُترك كما هو (rollback آمن)
 * لكن الـ queries لم تعد تستخدمه.
 */
return new class extends Migration
{
    /** حجم الدفعة في الـ backfill */
    private const CHUNK = 500;

    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! Schema::hasColumn('search_indices', 'search_text')) {
            Schema::table('search_indices', function (Blueprint $table) {
                $table->longText('search_text')
                    ->nullable()
                    ->after('meta')
                    ->comment('نص مُطبَّع (title+content+meta+مصطلحات عبر-لغوية) — هذا ما يُطابقه FULLTEXT');
            });
        }

        if (! $isSqlite && ! $this->fulltextIndexExists()) {
            DB::statement('ALTER TABLE search_indices ADD FULLTEXT fulltext_search_text (search_text)');
        }

        /*
         | لا يُملأ search_text هنا بعد اليوم.
         |
         | العمود يُسقطه ترحيل لاحق حين حلّت محلّه أعمدة مطويّة منفصلة
         | لكل حقل (title_fold / content_fold / meta_fold)، فملؤه على
         | قاعدة جديدة عملٌ يُلقى فوراً.
         |
         | ويبقى الترحيل نفسه قائماً لا محذوفاً: قواعد قائمة طبّقته
         | سلفاً، وحذفه يترك عمودها بلا تاريخ يفسّره ولا ترحيل يُسقطه.
         */
        $this->backfillDataTypeSlug();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE search_indices DROP INDEX fulltext_search_text');
        }

        Schema::table('search_indices', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }

    // ─────────────────────────────────────────────────────────────────

    private function fulltextIndexExists(): bool
    {
        return DB::select(
            "SHOW INDEX FROM search_indices WHERE Key_name = 'fulltext_search_text'"
        ) !== [];
    }

    private function backfillDataTypeSlug(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasColumn('search_indices', 'data_type_slug')) {
            return;
        }

        DB::table('search_indices')
            ->whereNull('data_type_slug')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) {
                $slugs = DB::table('data_types')
                    ->whereIn('id', $rows->pluck('data_type_id')->unique()->all())
                    ->pluck('slug', 'id');

                foreach ($rows as $row) {
                    $slug = $slugs[$row->data_type_id] ?? null;

                    if ($slug !== null) {
                        DB::table('search_indices')
                            ->where('id', $row->id)
                            ->update(['data_type_slug' => $slug]);
                    }
                }
            });
    }
};
