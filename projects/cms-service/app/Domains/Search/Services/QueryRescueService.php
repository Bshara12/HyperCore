<?php

declare(strict_types=1);

namespace App\Domains\Search\Services;

use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Rescue\KeyboardLayoutMapper;
use App\Domains\Search\Support\Rescue\VocabularyMatcher;

/**
 * QueryRescueService — إنقاذ محلّي لاستعلام لم يجد شيئاً.
 *
 * ─── موقعه في المسار ────────────────────────────────────────────────
 *
 *     فهم محلّي  →  استرجاع  →  صفر نتائج؟
 *                                    │
 *                                    ├── ① إنقاذ محلّي   ← هنا
 *                                    │      حتمي، بلا شبكة، ميلي ثوانٍ
 *                                    │
 *                                    └── ② احتياطي ذكي
 *                                           عند فشل الأول فقط
 *
 * الترتيب ليس اعتباطياً. أشيع سببين لصفر النتائج — خطأ مطبعي وخطأ
 * لسان الإدخال — كلاهما يُحلّ حتمياً بلا نموذج لغوي. إرسالهما إلى
 * الشبكة يدفع كلفة وزمناً مقابل إجابة كان يمكن اشتقاقها يقيناً.
 *
 * ─── الاستراتيجيتان ────────────────────────────────────────────────
 *
 * تخطيط لوحة المفاتيح أولاً، لأن كشفه قاطع: "هحاخىث" ليست كلمة في
 * أي لغة، وعكسها يعطي "iphone" بيقين لا باحتمال. أمّا التصحيح
 * الإملائي فاستدلال احتمالي، فيأتي بعده.
 *
 * ─── شرط القبول الوحيد ─────────────────────────────────────────────
 *
 * لا يقرّر هذا الصنف صحّة شيء — يقترح خططاً بديلة مرتّبة، والمستدعي
 * يجرّبها ويقبل أوّل ما يجد نتائج. فمهما بدا الاقتراح معقولاً، خطّة
 * لا تطابق شيئاً في الفهرس بلا قيمة.
 */
final class QueryRescueService
{
    public function __construct(
        private readonly QueryAnalyzer $analyzer,
        private readonly KeyboardLayoutMapper $keyboard,
        private readonly VocabularyMatcher $vocabulary,
    ) {}

    /**
     * خطط بديلة مرتّبة بالأرجحية.
     *
     * @return array<int, array{plan: QueryPlan, strategy: string}>
     */
    public function candidates(QueryPlan $plan, int $projectId, string $language): array
    {
        if ($plan->folded === '') {
            return [];
        }

        $candidates = [];

        foreach ($this->fromKeyboardLayout($plan, $projectId, $language) as $candidate) {
            $candidates[] = $candidate;
        }

        $spelling = $this->fromSpelling($plan, $projectId, $language);

        if ($spelling !== null) {
            $candidates[] = $spelling;
        }

        return $candidates;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * إعادة تفسير النصّ كأنه كُتب بلوحة مفاتيح أخرى.
     *
     * يُعاد تحليل النصّ المعكوس من الصفر لا تُبدَّل مصطلحاته فحسب:
     * العكس قد يكشف دالّاً زمنياً أو نفياً أو نيّةً لم تكن ظاهرة في
     * الصورة المشوَّهة، وتخطّي التحليل يُضيّع ذلك كله.
     *
     * @return array<int, array{plan: QueryPlan, strategy: string}>
     */
    private function fromKeyboardLayout(QueryPlan $plan, int $projectId, string $language): array
    {
        $candidates = [];

        foreach ($this->keyboard->candidates($plan->original) as $remapped) {
            $rescued = $this->analyzer->analyze($remapped, $plan->dataTypeSlug, $projectId, $language);

            if (! $rescued->isExecutable()) {
                continue;
            }

            /*
             | الحارس: هل يعرف المشروع أيّاً من الكلمات الناتجة؟
             |
             | العكس ميكانيكي وينجح على أي نصّ — كل حرف له نظير — فمخرَجه
             | دائماً "كلمات" شكلاً. والسؤال ليس هل نجح العكس بل هل كان
             | النصّ مكتوباً بلوحة خاطئة أصلاً.
             |
             | بلا هذا الحارس مرّت جملة عربية سليمة فأنتجت "f d hbdt k
             | dgd g fhg"، وطابقت البادئتان f* و k* كلمتَي Fashion
             | وKitchen، فعادت سبع عشرة نتيجة لا صلة لها بالاستعلام.
             */
            if (! $this->vocabulary->knowsAny($rescued->terms, $projectId, $language)) {
                continue;
            }

            $candidates[] = [
                'plan' => $rescued->withSource('keyboard'),
                'strategy' => 'keyboard',
            ];
        }

        return $candidates;
    }

    /**
     * تصحيح المصطلحات إلى أقرب كلمات موجودة في مفردات المشروع.
     *
     * تُصحَّح المصطلحات وحدها ويبقى ما عداها — الشروط البنيوية والنيّة
     * والاستثناءات — كما فهمه المحلّل. الخطأ المطبعي في كلمة لا يبطل
     * ما فُهم من بقية الجملة: من كتب "smartfone released in 2020" أخطأ
     * في كلمة واحدة وأصاب في الشرط الزمني.
     *
     * @return array{plan: QueryPlan, strategy: string}|null
     */
    private function fromSpelling(QueryPlan $plan, int $projectId, string $language): ?array
    {
        if ($plan->terms === []) {
            return null;
        }

        $corrected = $this->vocabulary->correctAll($plan->terms, $projectId, $language);

        if ($corrected === null) {
            return null;
        }

        return [
            'plan' => $plan->withTerms($corrected)->withSource('spelling'),
            'strategy' => 'spelling',
        ];
    }
}
