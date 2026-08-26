<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Lexicon;

use App\Domains\Search\Support\Text\TextFolder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProjectSynonyms — المرادفات المعتمَدة لكل مشروع.
 *
 * ─── مصدرها ─────────────────────────────────────────────────────────
 *
 * جدول synonym_suggestions يملؤه محلّل التواردات: كلمتان ترد إحداهما
 * مكان الأخرى في استعلامات المستخدمين ونقراتهم تُقترَحان مترادفتين،
 * ثم يعتمدهما مشرف. المعتمَد فقط يصل إلى هنا.
 *
 * وهذا هو الفرق بين المرادفات العامّة والمرادفات المتعلّمة: المعجم
 * الثابت يعرف أن "phone" و"mobile" مترادفتان في كل مكان، أمّا أن
 * "قماش" و"خام" مترادفتان في متجر أقمشة بعينه فلا يعرفه إلا سلوك
 * مستخدميه.
 *
 * ─── لماذا تُطبَّق كتوسعة لا كمصطلح ────────────────────────────────
 *
 * المرادف استنتاج إحصائي، وقد يخطئ. إدخاله في المصطلحات المُلزَمة
 * يعني أن خطأً في الاقتراح يقلب نتائج البحث. أمّا كتوسعة فيوسّع
 * الاسترجاع بوزن نصفي: يلتقط ما كان يضيع، ولا يزاحم ما كتبه المستخدم.
 *
 * ─── الكاش ──────────────────────────────────────────────────────────
 *
 * طبقتان: ذاكرة داخل النسخة للطلب الواحد، وكاش التطبيق عبر الطلبات.
 * الخريطة تتغيّر عند اعتماد مشرف لاقتراح — أي مرّات معدودة في اليوم —
 * فساعة من التقادم مقبولة تماماً، ويُبطلها الاعتماد صراحةً.
 */
final class ProjectSynonyms
{
    private const CACHE_TTL_SECONDS = 3600;

    private const MAX_PER_TERM = 3;

    /** الحدّ الأدنى للثقة كي يدخل الاقتراح المعتمَد في التوسعة. */
    private const MIN_CONFIDENCE = 0.5;

    /**
     * @var array<string, array<string, string[]>>
     */
    private array $instanceCache = [];

    /**
     * مرادفات مجموعة مصطلحات.
     *
     * @param  string[]  $terms
     * @return string[] مرادفات مسطَّحة، خالية من المصطلحات الأصلية
     */
    public function expand(array $terms, int $projectId, string $language): array
    {
        if ($terms === []) {
            return [];
        }

        $map = $this->map($projectId, $language);

        if ($map === []) {
            return [];
        }

        $original = array_flip($terms);
        $expansions = [];

        foreach ($terms as $term) {
            foreach ($map[$term] ?? [] as $synonym) {
                if (! isset($original[$synonym])) {
                    $expansions[$synonym] = true;
                }
            }
        }

        return array_keys($expansions);
    }

    public function invalidate(int $projectId, string $language): void
    {
        unset($this->instanceCache["{$projectId}:{$language}"]);
        Cache::forget($this->cacheKey($projectId, $language));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string[]>
     */
    private function map(int $projectId, string $language): array
    {
        $key = "{$projectId}:{$language}";

        if (isset($this->instanceCache[$key])) {
            return $this->instanceCache[$key];
        }

        try {
            $map = Cache::remember(
                $this->cacheKey($projectId, $language),
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->load($projectId, $language)
            );
        } catch (\Throwable $e) {
            /*
             | تعذّر تحميل المرادفات لا يبطل البحث.
             |
             | التوسعة تحسين لا شرط: البحث بمصطلحات المستخدم وحدها يبقى
             | صحيحاً وإن كان أضيق. إسقاط البحث كلّه لأن جدول اقتراحات
             | تعذّرت قراءته مقايضة خاسرة.
             */
            Log::warning('ProjectSynonyms: failed to load map', [
                'project_id' => $projectId,
                'language' => $language,
                'error' => $e->getMessage(),
            ]);

            $map = [];
        }

        return $this->instanceCache[$key] = $map;
    }

    /**
     * @return array<string, string[]>
     */
    private function load(int $projectId, string $language): array
    {
        $rows = DB::table('synonym_suggestions')
            ->select('word_a', 'word_b')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->where('status', 'approved')
            ->where('confidence_score', '>=', self::MIN_CONFIDENCE)
            ->orderByDesc('confidence_score')
            ->limit(2000)
            ->get();

        $map = [];

        foreach ($rows as $row) {
            /*
             | المرادفات تُطبَّع هنا بنفس دالة تطبيع الاستعلام.
             |
             | الكلمات دخلت الجدول من سجلّات بحث خام قد تحمل تشكيلاً أو
             | حروفاً كاملة العرض. بلا تطبيعها لن تطابق مصطلحات الخطة
             | أبداً، فتبدو الميزة عاملةً وهي لا تفعل شيئاً.
             */
            $a = TextFolder::fold((string) $row->word_a);
            $b = TextFolder::fold((string) $row->word_b);

            if ($a === '' || $b === '' || $a === $b) {
                continue;
            }

            // العلاقة متناظرة: من بحث بأيّهما يجد الآخر.
            $this->link($map, $a, $b);
            $this->link($map, $b, $a);
        }

        return $map;
    }

    /**
     * @param  array<string, string[]>  $map
     */
    private function link(array &$map, string $from, string $to): void
    {
        $existing = $map[$from] ?? [];

        if (count($existing) >= self::MAX_PER_TERM || in_array($to, $existing, true)) {
            return;
        }

        $existing[] = $to;
        $map[$from] = $existing;
    }

    private function cacheKey(int $projectId, string $language): string
    {
        return "search:synonyms:{$projectId}:{$language}";
    }
}
