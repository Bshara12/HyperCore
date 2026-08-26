<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إحصاءات المتن — مدخلات BM25.
 *
 * ─── لماذا نحتاجها أصلاً ────────────────────────────────────────────
 *
 * كان الترتيب يعتمد على قيمة MATCH() الخام من MySQL، وفيها ثلاث علل:
 *
 *   1. غامضة: صيغة داخلية غير موثَّقة ولا مضبوطة، لا يمكن تفسير
 *      نتيجتها ولا ضبط سلوكها.
 *
 *   2. لا تعرف الحقول: MATCH(title, content) يسطّح العمودين في نصّ
 *      واحد، فمطابقة العنوان تساوي مطابقة الحاشية. وهي أقوى إشارة
 *      صلة في البحث ويجري إهدارها.
 *
 *   3. IDF على مستوى الجدول: التكرار المستندي محسوب عبر كل المشاريع
 *      وكل اللغات معاً. في جدول مشترك يصير "iphone" شائعاً عالمياً
 *      فيفقد تمييزه داخل مشروع تكون فيه الكلمة نادرة ودالّة.
 *
 * BM25F يعالج الثلاثة، ويحتاج مقابل ذلك إحصاءَين محفوظَين: عدد
 * المستندات وأطوالها الوسطى (هذا الجدول)، والتكرار المستندي لكل
 * مصطلح (الجدول الثاني).
 *
 * ─── لماذا محفوظة لا محسوبة لحظياً ─────────────────────────────────
 *
 * حساب IDF لحظياً يعني COUNT على كل مصطلح في كل بحث. مع ستة مصطلحات
 * فهذه ستّة استعلامات تجميعية قبل أن يبدأ البحث الفعلي. الإحصاءات
 * تتغيّر ببطء — بمعدّل تغيّر المحتوى لا بمعدّل البحث — فمكانها
 * الطبيعي مهمّة دورية لا المسار الحيّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | إحصاءات على مستوى المتن: مقام IDF وتطبيع الطول.
         |
         | التجزئة بالمشروع واللغة مقصودة: مشروع عربي ومشروع إنجليزي
         | في الجدول نفسه لهما مفردات وأطوال مختلفة تماماً، وخلط
         | إحصاءاتهما يفسد الترتيب في كليهما.
         */
        Schema::create('search_corpus_stats', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->string('language', 10);

            $table->unsignedBigInteger('doc_count')->default(0);

            $table->decimal('avg_title_terms', 10, 4)->default(0);
            $table->decimal('avg_content_terms', 10, 4)->default(0);
            $table->decimal('avg_meta_terms', 10, 4)->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'language'], 'scs_project_lang_unique');
        });

        /*
         | التكرار المستندي لكل مصطلح: كم مستنداً يحتوي هذا المصطلح.
         |
         | هذا ما يجعل النظام يعرف أن "the" و"في" لا تميّز شيئاً بينما
         | "titanium" تميّز كثيراً — بلا قائمة كلمات وقف مكتوبة يدوياً
         | ولأي لغة كانت. كلمات الوقف في المعجم تحسّن الأداء، أمّا
         | التمييز الحقيقي فيأتي من هنا، إحصائياً.
         */
        Schema::create('search_term_stats', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->string('language', 10);

            /*
             | المصطلح بصورته المطبَّعة. الطول 64 يكفي أطول الكلمات
             | الطبيعية ووحدات الـ n-gram على حدّ سواء.
             */
            $table->string('term', 64);

            $table->unsignedBigInteger('doc_freq')->default(0);

            $table->timestamps();

            $table->unique(['project_id', 'language', 'term'], 'sts_term_unique');

            /*
             | فهرس التنقية: يخدم حذف المصطلحات النادرة عند إعادة
             | الحساب، ويمنع نموّ الجدول بلا حدّ مع كل خطأ مطبعي
             | في المحتوى.
             */
            $table->index(['project_id', 'language', 'doc_freq'], 'sts_freq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_term_stats');
        Schema::dropIfExists('search_corpus_stats');
    }
};
