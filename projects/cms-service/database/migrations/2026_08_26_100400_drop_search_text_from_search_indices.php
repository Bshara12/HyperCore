<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط search_text — حلّت محلّه أعمدة مطويّة منفصلة لكل حقل.
 *
 * ─── ما كان يفعله ───────────────────────────────────────────────────
 *
 * أضافه ترحيل 2026_08_20_000001 لعلاج مشكلتين حقيقيتين: النصّ العربي
 * كان مفهرساً بصورته الخام فلا يطابقه استعلام مطبَّع، وقيم meta كانت
 * خارج الفهرس كلياً. وقد عالجهما بجمع كل النصوص في عمود واحد مطبَّع
 * وفهرسته.
 *
 * ─── لماذا يُسقَط ───────────────────────────────────────────────────
 *
 * الجمع في عمود واحد هو نفسه ما يمنع ترجيح الحقول. حين يصير العنوان
 * والمتن وقيم الحقول نصّاً واحداً، تتساوى مطابقةُ العنوان بمطابقةِ
 * كلمة عابرة في حاشية — وهي أقوى إشارة صلة في البحث تُهدَر.
 *
 * والأعمدة الثلاثة (title_fold / content_fold / meta_fold) تؤدّي ما
 * كان يؤدّيه وتزيد: تمرّ بالتطبيع نفسه، وتشمل meta، ويزنها BM25F كلاً
 * على حدة. فبقاء search_text إلى جانبها تكرارٌ لنفس النصّ بصورة أدنى:
 * تخزينٌ مضاعف، وفهرس FULLTEXT ثانٍ يُصان عند كل كتابة، ومصدر حقيقة
 * ثانٍ يفترق عن الأول عند أول تعديل يُغفل أحدهما.
 *
 * ─── لماذا ترحيل جديد لا تعديل القديم ──────────────────────────────
 *
 * قواعد قائمة طبّقت الترحيل القديم سلفاً. تعديله في مكانه لا يُشغَّل
 * عليها ثانيةً، فيبقى العمود والفهرس فيها بلا شيء يُسقطهما. الترحيل
 * الأمامي وحده يصل إلى كل البيئات بالحالة نفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('search_indices', 'search_text')) {
            return;
        }

        /*
         | الفهرس يُسقَط قبل عموده: MySQL يرفض إسقاط عمود يشارك في فهرس
         | FULLTEXT قائم.
         */
        if (DB::connection()->getDriverName() !== 'sqlite' && $this->fulltextIndexExists()) {
            DB::statement('ALTER TABLE search_indices DROP INDEX fulltext_search_text');
        }

        Schema::table('search_indices', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('search_indices', 'search_text')) {
            return;
        }

        Schema::table('search_indices', function (Blueprint $table) {
            $table->longText('search_text')->nullable()->after('meta');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE search_indices ADD FULLTEXT fulltext_search_text (search_text)');
        }

        /*
         | يُستعاد العمود فارغاً.
         |
         | ملؤه يحتاج SearchTextBuilder وقد أُزيل، والتراجع إلى تصميم
         | متجاوَز يستوجب إعادة فهرسة كاملة على أي حال:
         |
         |     php artisan search:reindex --force
         */
    }

    private function fulltextIndexExists(): bool
    {
        return DB::select(
            "SHOW INDEX FROM search_indices WHERE Key_name = 'fulltext_search_text'"
        ) !== [];
    }
};
