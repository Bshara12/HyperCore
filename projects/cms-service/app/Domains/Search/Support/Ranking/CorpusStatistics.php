<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

/**
 * CorpusStatistics — الإحصاءات التي يحتاجها BM25 عن متن مشروع ولغة.
 *
 * تُقرأ مرّة لكل بحث وتُمرَّر إلى المُسجِّل، فلا يلمس حساب الترتيب
 * قاعدة البيانات أثناء تسجيل النقاط.
 */
final readonly class CorpusStatistics
{
    /**
     * @param  array<string, int>  $documentFrequencies  المصطلح => عدد المستندات الحاوية له
     */
    public function __construct(
        public int $documentCount,
        public float $avgTitleTerms,
        public float $avgContentTerms,
        public float $avgMetaTerms,
        public array $documentFrequencies = [],
    ) {}

    /**
     * إحصاءات بديلة لمتن لم تُحسب إحصاءاته بعد.
     *
     * ─── لماذا هي ضرورية وليست ترفاً ───────────────────────────────
     *
     * المهمّة الدورية قد لم تكن جرت بعد على مشروع جديد، أو أُضيف
     * محتوى بعد آخر حساب. بلا بديل معقول يصير مقام IDF صفراً فينهار
     * الترتيب كلّه إلى صفر — أي بحث بلا ترتيب.
     *
     * الأطوال هنا تقديرات لمحتوى CMS نمطي؛ أثرها محدود لأن تطبيع
     * الطول نسبيّ: ما دامت التقديرات متّسقة عبر المستندات فترتيبها
     * فيما بينها يبقى صحيحاً وإن انزاح المقياس المطلق.
     */
    public static function fallback(): self
    {
        return new self(
            documentCount: 1,
            avgTitleTerms: 6.0,
            avgContentTerms: 120.0,
            avgMetaTerms: 8.0,
        );
    }

    /**
     * التكرار المستندي لمصطلح.
     *
     * المصطلح غير المعروف يُعامَل بتكرار 1 لا 0: مصطلح لم نرَه في
     * الإحصاءات إمّا نادر جداً أو حديث، وكلاهما يعني أنه شديد
     * التمييز — وهو بالضبط ما يعبّر عنه تكرار 1.
     */
    public function documentFrequency(string $term): int
    {
        return max(1, $this->documentFrequencies[$term] ?? 1);
    }

    /**
     * التكرار المستندي المعكوس (IDF) بصيغة BM25 الاحتمالية.
     *
     *     idf = ln( 1 + (N - df + 0.5) / (df + 0.5) )
     *
     * الإضافة "1 +" ليست تجميلاً: بدونها تصير IDF سالبة للمصطلحات
     * التي ترد في أكثر من نصف المستندات، فيعاقب النظام المستندَ على
     * احتوائه كلمةً بحث عنها المستخدم. وهذا هو ما يجعل الصيغة تتعامل
     * مع كلمات الوقف بلا حاجة إلى قائمة: كلمة ترد في كل مكان تحصل
     * على وزن يقارب الصفر، لا على وزن سالب.
     */
    public function inverseDocumentFrequency(string $term): float
    {
        $df = $this->documentFrequency($term);
        $n = max(1, $this->documentCount);

        return log(1.0 + (($n - $df + 0.5) / ($df + 0.5)));
    }

    public function averageLengthFor(string $field): float
    {
        return max(1.0, match ($field) {
            'title' => $this->avgTitleTerms,
            'meta' => $this->avgMetaTerms,
            default => $this->avgContentTerms,
        });
    }
}
