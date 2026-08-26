<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول السمات البنيوية — الطبقة التي تجعل "الايفون يلي نزل بال 2020" ممكناً.
 *
 * ─── لماذا جدول منفصل لا أعمدة ─────────────────────────────────────
 *
 * حقول المحتوى في CMS يعرّفها المستخدم لا المطوّر: مشروع يحمل
 * release_year وآخر يحمل model_year وثالث يحمل مقاس الشاشة والوزن.
 * لا يمكن حجز عمود لكل احتمال، ولا يصحّ ترك الأمر لـ JSON غير مفهرس
 * كما كان الحال — حيث كان meta مخزَّناً وغير مفهرس ولا مبحوث فيه.
 *
 * النموذج هنا EAV مفهرس: صفّ لكل (مستند، سمة). التكلفة صفّ إضافي لكل
 * حقل، والعائد أن أي حقل مخصَّص يصير قابلاً للفلترة والترتيب بمجرّد
 * إدخاله، بلا هجرة ولا تعديل كود.
 *
 * ─── لماذا عمودان للقيمة ────────────────────────────────────────────
 *
 * قيمة نصّية وقيمة عددية منفصلتان لأن "128" النصّية أصغر من "64"
 * النصّية في الترتيب المعجمي. المقارنات العددية — وهي مقصد أغلب
 * الاستعلامات البنيوية ("أقل من 500"، "بعد 2018") — تحتاج نوعاً عددياً
 * حقيقياً لا سلسلة تُحوَّل عند كل مقارنة فيُهدَر الفهرس.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index_attributes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('project_id');
            $table->string('language', 10);

            /*
             | مفتاح مطبَّع: "Release Year" و"release_year" و"releaseYear"
             | تصل كلها بالصورة "release_year". بدون التطبيع يصير المفتاح
             | نفسه ثلاث سمات مختلفة حسب هجاء من أدخل الحقل.
             */
            $table->string('attr_key', 64);

            $table->string('value_text', 191)->nullable();
            $table->decimal('value_num', 20, 4)->nullable();

            $table->timestamps();

            /*
             | سمة واحدة بقيمة واحدة لكل مستند ولغة. القيود المتعدّدة
             | (ألوان متاحة مثلاً) تُميَّز بلاحقة في المفتاح لا بصفوف
             | مكرّرة، كي يبقى القيد قادراً على منع ازدواج الفهرسة.
             */
            $table->unique(
                ['entry_id', 'language', 'attr_key'],
                'sia_entry_lang_key_unique'
            );

            /*
             | فهرس الفلترة النصّية: (project, language, key, value).
             |
             | ترتيب الأعمدة يتبع انتقائيتها في الاستعلام الفعلي —
             | المشروع دائماً محدَّد، ثم اللغة، ثم المفتاح، ثم القيمة.
             */
            $table->index(
                ['project_id', 'language', 'attr_key', 'value_text'],
                'sia_text_lookup_idx'
            );

            /*
             | فهرس المدى العددي: يخدم =، و>=، و<=، وBETWEEN على value_num
             | لأن العمود آخر جزء في المفتاح المركَّب.
             */
            $table->index(
                ['project_id', 'language', 'attr_key', 'value_num'],
                'sia_numeric_range_idx'
            );

            /*
             | فهرس الحذف عند إعادة الفهرسة أو حذف المستند.
             */
            $table->index(['entry_id', 'language'], 'sia_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_attributes');
    }
};
