<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ذاكرة خطط الاستعلام — ما يجعل الاحتياطي الذكي مقبول الكلفة.
 *
 * ─── المشكلة الاقتصادية ─────────────────────────────────────────────
 *
 * كان النظام يستدعي نموذجاً لغوياً لكل استعلام عربي تقريباً. البحث
 * الواحد يتكرّر آلاف المرات، والاستعلامات الشائعة تشكّل الأغلبية
 * الساحقة من حركة أي محرك بحث. أي أن النظام كان يدفع الكلفة نفسها
 * مرارًا للسؤال نفسه، ويحصل في كل مرة على إجابة قد تختلف.
 *
 * ─── الحل ───────────────────────────────────────────────────────────
 *
 * بصمة الاستعلام المطبَّع مفتاحاً، والخطة الناتجة قيمةً. كل استعلام
 * مميّز يكلّف استدعاءً واحداً على الأكثر خلال مدة الصلاحية.
 *
 * ولماذا في قاعدة البيانات لا في Redis؟
 *   لأن هذه الخطط أصل معرفي لا كاش. هي حصيلة فهم النظام لاستعلامات
 *   مستخدميه، وتصلح لتحليل ما يبحثون عنه، ولتوليد المرادفات، ولملء
 *   المعجم المحلّي بما يغني عن النموذج لاحقاً. إتلافها عند إعادة
 *   تشغيل Redis خسارة لا داعي لها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_query_plans', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->string('language', 10);

            /*
             | بصمة النصّ المطبَّع لا الخام: "IPHONE" و"iphone"
             | و"ＩＰＨＯＮＥ" استعلام واحد، فيكفيها مدخل واحد.
             */
            $table->char('query_hash', 32);

            $table->string('original_query', 255);

            /*
             | الخطة مُسلسلة. تُقولب عند القراءة في QueryPlan نفسها
             | التي ينتجها المحلّل المحلّي — فلا يوجد مسار تنفيذ ثانٍ
             | خاصّ بمخرجات النموذج، وبالتالي لا سطح هجوم ثانٍ.
             */
            $table->json('plan');

            $table->string('provider', 32)->nullable();
            $table->decimal('confidence', 5, 4)->default(0);

            /*
             | عدّاد الإصابات: يكشف الاستعلامات التي تستحقّ أن تُنقل
             | إلى المعجم المحلّي فيُستغنى عن النموذج فيها نهائياً.
             */
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['project_id', 'language', 'query_hash'],
                'sqp_lookup_unique'
            );

            /*
             | فهرس التنقية الدورية للخطط المنتهية الصلاحية.
             */
            $table->index('expires_at', 'sqp_expiry_idx');

            /*
             | فهرس التحليل: أكثر الخطط استعمالاً في مشروع ولغة.
             */
            $table->index(
                ['project_id', 'language', 'hit_count'],
                'sqp_popularity_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_plans');
    }
};
