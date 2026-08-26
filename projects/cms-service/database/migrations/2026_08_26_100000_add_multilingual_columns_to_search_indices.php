<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جعل الفهرس متعدّد اللغات فعلاً.
 *
 * ─── المشكلة ────────────────────────────────────────────────────────
 *
 * كان الفهرس يحمل النصّ الخام في title/content وفوقه فهرس FULLTEXT
 * واحد بالـ parser الافتراضي. ونتج عن ذلك ثلاثة إخفاقات:
 *
 *   1. الـ parser الافتراضي يقسّم على المسافات والترقيم. اللغات التي
 *      لا تضع مسافات — الصينية واليابانية والتايلندية والخميرية —
 *      تدخل الفهرس جملةً واحدة، فلا يطابقها أي استعلام عملياً.
 *
 *   2. النصّ مفهرس بصورته الخام. "قَهْوَة" و"قهوه" رمزان مختلفان في
 *      الفهرس، و"café" لا يطابق "cafe"، و"٢٠٢٠" لا يطابق "2020".
 *
 *   3. عمود meta كان مخزَّناً وغير مفهرس أصلاً — أي أن كل الحقول
 *      المخصَّصة (سنة الإصدار، السعر، اللون، المواصفات) كانت خارج
 *      البحث تماماً رغم أنها بالضبط ما يسأل عنه المستخدمون.
 *
 * ─── الحل ───────────────────────────────────────────────────────────
 *
 *   عمودان مطبَّعان (title_fold / content_fold) يمرّان بنفس دالة
 *   التطبيع التي يمرّ بها الاستعلام، وفهرس FULLTEXT ثانٍ بالـ parser
 *   ngram على عمود مخصَّص للغات بلا مسافات. النصّ الأصلي يبقى في
 *   title/content للعرض والتظليل.
 *
 *   فهرسان لا واحد لأن الـ parser خاصية للفهرس لا للاستعلام: لا يمكن
 *   لفهرس واحد أن يقسّم الإنجليزية بالكلمات والصينية بالـ n-grams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_indices', function (Blueprint $table) {
            /*
             | الصورة المطبَّعة للعنوان. VARCHAR(512) لا TEXT كي يبقى
             | العمود قابلاً للفهرسة بمفتاح عادي عند الحاجة.
             */
            $table->string('title_fold', 512)
                ->nullable()
                ->after('title');

            /*
             | الصورة المطبَّعة للمتن.
             */
            $table->longText('content_fold')
                ->nullable()
                ->after('content');

            /*
             | الصورة المطبَّعة لقيم الحقول المخصَّصة.
             |
             | عمود مستقلّ لا مدموج بالمتن، لأن BM25F يزن الحقول: قيمة
             | حقل مخصَّص ("Titanium"، "Black") أدلّ من ورودها عرضاً في
             | فقرة، وأقلّ دلالةً من ورودها في العنوان. دمجها بالمتن
             | كان سيجعل وزن الحقول الثلاثة اثنين فعلياً.
             |
             | ووجودها هنا هو ما يجعل الحقول المخصَّصة قابلة للبحث الحرّ
             | أصلاً: جدول السمات يخدم الشروط الدقيقة ("سنة = 2020")،
             | أمّا "ايفون تيتانيوم" فيحتاج القيمة ضمن نصّ مفهرس.
             */
            $table->longText('meta_fold')
                ->nullable()
                ->after('content_fold');

            /*
             | نصّ الـ n-gram: يُملأ فقط للمستندات التي تحتوي فعلاً
             | script بلا مسافات. يبقى NULL في المشاريع اللاتينية
             | والعربية فلا يكلّف تخزيناً ولا صيانة فهرس.
             */
            $table->longText('ngram_text')
                ->nullable()
                ->after('meta_fold');

            /*
             | نظام الكتابة المهيمن على المستند. يُستعمل للتشخيص
             | ولاختيار الفهرس المستهدَف بلا إعادة تحليل النصّ.
             */
            $table->string('script', 8)
                ->nullable()
                ->after('language');

            /*
             | طول المستند بالوحدات لكل حقل — مدخلات BM25.
             |
             | تُحسب عند الفهرسة لا عند الاستعلام: حسابها لحظياً يعني
             | تفكيك نصّ كل مرشَّح في كل بحث، وهو أثقل جزء في العملية
             | كلها وأكثرها قابلية للحساب المسبق.
             */
            $table->unsignedInteger('title_terms')->default(0)->after('title_length');
            $table->unsignedInteger('content_terms')->default(0)->after('title_terms');
            $table->unsignedInteger('meta_terms')->default(0)->after('content_terms');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        /*
         | الفهرس الأساسي: parser افتراضي على الأعمدة المطبَّعة.
         |
         | الفهرس القديم على (title, content) الخامَّين يُترك قائماً
         | عمداً في هذه الهجرة: إسقاطه قبل امتلاء الأعمدة الجديدة
         | يترك البحث معطَّلاً بين الترحيل وإعادة الفهرسة.
         | تُسقطه هجرة لاحقة بعد نجاح search:reindex.
         */
        DB::statement('
            ALTER TABLE search_indices
            ADD FULLTEXT ft_fold (title_fold, content_fold, meta_fold)
        ');

        /*
         | فهرس الـ ngram: يحتاج WITH PARSER ngram، وهو مدمج في
         | MySQL 8 ولا يتطلّب أي إضافة خارجية.
         |
         | حجم الـ n-gram يضبطه المتغيّر الخادمي innodb_ngram_token_size
         | (افتراضياً 2) ويجب أن يساوي search.indexing.ngram_token_size،
         | وإلا بحثنا عن وحدات غير التي فُهرست فلا تطابق أبداً.
         */
        DB::statement('
            ALTER TABLE search_indices
            ADD FULLTEXT ft_ngram (ngram_text) WITH PARSER ngram
        ');

        DB::statement('
            ALTER TABLE search_indices
            ADD INDEX si_script_idx (project_id, language, script)
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE search_indices DROP INDEX ft_fold');
            DB::statement('ALTER TABLE search_indices DROP INDEX ft_ngram');
            DB::statement('ALTER TABLE search_indices DROP INDEX si_script_idx');
        }

        Schema::table('search_indices', function (Blueprint $table) {
            $table->dropColumn([
                'title_fold',
                'content_fold',
                'meta_fold',
                'ngram_text',
                'script',
                'title_terms',
                'content_terms',
                'meta_terms',
            ]);
        });
    }
};
