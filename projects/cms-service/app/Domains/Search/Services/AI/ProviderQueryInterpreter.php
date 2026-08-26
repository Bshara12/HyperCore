<?php

declare(strict_types=1);

namespace App\Domains\Search\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * ProviderQueryInterpreter — تفسير عبر سلسلة مزوّدين مع تعاقب.
 *
 * ─── لماذا سلسلة ────────────────────────────────────────────────────
 *
 * مزوّدو النماذج اللغوية يعطبون ويحدّون المعدّل ويغيّرون أسماء نماذجهم.
 * الاعتماد على واحد يعني أن عطبه يُسقط الميزة كلّها. السلسلة تنتقل
 * إلى التالي عند إخفاق الأول.
 *
 * ─── لماذا لا يعيد نصّ استعلام ─────────────────────────────────────
 *
 * المزوّدون يعرضون normalize() التي تعيد "استعلاماً مطبَّعاً" — سلسلة
 * نصّية حرّة. وقد كان الإصدار السابق يمرّرها إلى نحو BOOLEAN MODE بعد
 * تقسيم ساذج على المسافات.
 *
 * هنا تُفكَّك السلسلة إلى مصطلحات ويُفصَل ما سبقه معامل نفي، ثم لا
 * يخرج من هذا الصنف إلا مصفوفتان من الكلمات. فما يعود من الشبكة
 * بياناتٌ لا تعليمات.
 */
final class ProviderQueryInterpreter implements QueryInterpreterInterface
{
    /**
     * @param  AIProviderInterface[]  $providers  مرتّبة حسب الأفضلية
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function interpret(string $query, string $language): ?array
    {
        $timeout = (float) config('search.ai.timeout_seconds', 3.0);
        $deadline = microtime(true) + $timeout;

        foreach ($this->providers as $provider) {
            /*
             | المهلة محسوبة على السلسلة كلّها لا على كل مزوّد.
             |
             | مهلة لكل مزوّد تعني أن ثلاثة مزوّدين بمهلة ثلاث ثوانٍ
             | لكلٍّ يمكن أن يستغرقوا تسعاً — أي أن الحدّ الذي وضعناه
             | لزمن الاستجابة لا يحدّ شيئاً.
             */
            if (microtime(true) >= $deadline) {
                Log::info('ProviderQueryInterpreter: deadline exhausted', ['query' => $query]);

                break;
            }

            try {
                $result = $provider->normalize($query, $language);
            } catch (\Throwable $e) {
                Log::warning('ProviderQueryInterpreter: provider failed', [
                    'provider' => $provider->name(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $interpretation = $this->parse($result, $provider->name());

            if ($interpretation !== null) {
                return $interpretation;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $result
     * @return array{include: string[], exclude: string[], confidence: float, source: string}|null
     */
    private function parse(array $result, string $source): ?array
    {
        $normalized = trim((string) ($result['normalized_query'] ?? ''));

        if ($normalized === '') {
            return null;
        }

        $include = [];
        $exclude = [];

        foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            /*
             | معامل النفي يُفسَّر هنا ولا يُمرَّر.
             |
             | تركُه في السلسلة كان يعني وصوله إلى نحو BOOLEAN MODE
             | حيث له معنى تنفيذي. تفسيرُه إلى قائمة استثناء يجعله
             | بياناً في بنيتنا لا أمراً في بنية غيرنا.
             */
            if (str_starts_with($word, '-') && mb_strlen($word, 'UTF-8') > 1) {
                $exclude[] = mb_substr($word, 1, null, 'UTF-8');

                continue;
            }

            $include[] = ltrim($word, '+');
        }

        if ($include === []) {
            return null;
        }

        return [
            'include' => $include,
            'exclude' => $exclude,
            'confidence' => min(1.0, max(0.0, (float) ($result['confidence'] ?? 0.0))),
            'source' => $source,
        ];
    }
}
