<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Text;

/**
 * Segmenter — تقسيم النص إلى وحدات قابلة للفهرسة، لأي script.
 *
 * المشكلة التي يحلّها:
 *   الـ parser الافتراضي في MySQL FULLTEXT يقسّم على المسافات والترقيم.
 *   هذا يعمل مع اللاتيني والعربي والكيريلي، ويفشل فشلاً تاماً مع الصينية
 *   واليابانية والكورية والتايلندية والخميرية — لغات لا تضع مسافات بين
 *   الكلمات. الجملة "我想买苹果手机" يراها الـ parser رمزاً واحداً، فلا
 *   يطابقها أي استعلام إلا إذا كُتب حرفياً بالكامل.
 *
 * الحل:
 *   نقسّم النص إلى مقاطع (runs) حسب الـ script، ونعامل كل مقطع بما يناسبه:
 *     - script بمسافات    → تقسيم على حدود الكلمات
 *     - script بلا مسافات → n-grams على مستوى الحرف
 *
 *   الـ n-grams ليست حلاً وسطاً رديئاً — هي المقاربة القياسية للبحث في
 *   الصينية عندما لا يتوفر قاموس تقطيع. بحجم 2 تلتقط أغلب الكلمات
 *   الصينية (ثنائية المقاطع غالباً) بدون أي بيانات لغوية.
 *
 * حجم الـ n-gram هنا يجب أن يطابق innodb_ngram_token_size في MySQL،
 * وإلا اختلف ما نبحث عنه عمّا فُهرس. الافتراضي في MySQL هو 2.
 */
final class Segmenter
{
    public const NGRAM_SIZE = 2;

    private const MAX_TOKENS = 4096;

    /**
     * أقصى طول وحدة، مطابقاً لـ innodb_ft_max_token_size في MySQL.
     *
     * الوحدة الأطول من هذا الحدّ لا تدخل الفهرس إطلاقاً، فلا يمكن أن
     * تطابق شيئاً مهما فعلنا. وإبقاؤها يضرّ مرّتين: تُنتج تعبير بحث
     * محكوماً بالفشل، وتصل إلى طبقة الإنقاذ فتُقاس بخوارزميات لم
     * تُصمَّم لهذا المدى — وقد ظهر ذلك عملياً حين طوت metaphone سلسلة
     * من مئتَي حرف مكرَّر إلى رمز واحد، فبدت "قريبة" من كلمة حقيقية.
     */
    private const MAX_TOKEN_LENGTH = 84;

    /**
     * تقسيم نص مطبَّع إلى وحدات.
     *
     * المُدخل يجب أن يكون مرّ بـ TextFolder::fold() مسبقاً — هذه الدالة
     * لا تطبّع، فقط تقسّم.
     *
     * @return string[]
     */
    public static function tokenize(string $foldedText): array
    {
        if (trim($foldedText) === '') {
            return [];
        }

        $tokens = [];

        foreach (self::runs($foldedText) as [$script, $run]) {
            if (UnicodeScript::isUnsegmented($script)) {
                foreach (self::ngrams($run) as $gram) {
                    $tokens[] = $gram;
                }

                continue;
            }

            foreach (self::words($run) as $word) {
                $tokens[] = $word;
            }

            if (count($tokens) > self::MAX_TOKENS) {
                break;
            }
        }

        return array_slice($tokens, 0, self::MAX_TOKENS);
    }

    /**
     * نص الـ n-gram المخصَّص لفهرس الـ ngram في MySQL.
     *
     * يُعاد فقط ما كان بلا مسافات؛ المقاطع اللاتينية والعربية تُترك للفهرس
     * الأساسي، فلا داعي لمضاعفة تخزينها هنا.
     *
     * يُعاد null إذا لم يحتوِ النص أي script بلا مسافات — فيبقى العمود
     * NULL ولا يكلّف شيئاً في الجداول التي لا تحتوي محتوى آسيوياً.
     */
    public static function ngramText(string $foldedText): ?string
    {
        $segments = [];

        foreach (self::runs($foldedText) as [$script, $run]) {
            if (UnicodeScript::isUnsegmented($script)) {
                $segments[] = $run;
            }
        }

        if ($segments === []) {
            return null;
        }

        return implode(' ', $segments);
    }

    /**
     * تقسيم النص إلى مقاطع متجانسة الـ script.
     *
     * الأرقام والترقيم (COMMON) تُلحَق بالمقطع الجاري بدل أن تقطعه، حتى
     * يبقى "iphone 15" مقطعاً لاتينياً واحداً لا ثلاثة مقاطع.
     *
     * @return array<int, array{0:string,1:string}> [script, نص المقطع]
     */
    private static function runs(string $text): array
    {
        $runs = [];
        $currentScript = null;
        $current = '';

        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $script = UnicodeScript::ofCodepoint(mb_ord($char, 'UTF-8'));

            if ($script === UnicodeScript::COMMON) {
                $current .= $char;

                continue;
            }

            if ($currentScript !== null && $script !== $currentScript) {
                $runs[] = [$currentScript, $current];
                $current = '';
            }

            $currentScript = $script;
            $current .= $char;
        }

        if ($currentScript !== null && trim($current) !== '') {
            $runs[] = [$currentScript, $current];
        }

        return self::mergeUnsegmented($runs);
    }

    /**
     * دمج المقاطع المتجاورة التي لا تفصل كلماتها مسافات.
     *
     * اليابانية تخلط ثلاثة scripts داخل الكلمة الواحدة: "日本語のスマホ"
     * هي Han ثم Hiragana ثم Katakana. لو عاملنا كل مقطع وحده لما عبر أي
     * bigram الحدود، فيضيع "語の" و"のス" — وهي بالضبط الوحدات التي تحمل
     * البنية النحوية. الدمج يجعل الـ n-grams تمرّ عبر الجملة كاملة.
     *
     * الـ script المُسنَد للمقطع المدموج هو الأول: كلها غير مقسَّمة، وهو
     * كل ما يهمّ في القرار اللاحق.
     *
     * @param  array<int, array{0:string,1:string}>  $runs
     * @return array<int, array{0:string,1:string}>
     */
    private static function mergeUnsegmented(array $runs): array
    {
        $merged = [];

        foreach ($runs as [$script, $text]) {
            $previous = $merged === [] ? null : $merged[count($merged) - 1];

            if (
                $previous !== null
                && UnicodeScript::isUnsegmented($script)
                && UnicodeScript::isUnsegmented($previous[0])
            ) {
                $merged[count($merged) - 1][1] .= $text;

                continue;
            }

            $merged[] = [$script, $text];
        }

        return $merged;
    }

    /**
     * تقسيم مقطع ذي مسافات إلى كلمات.
     *
     * الشرطة والشرطة السفلية والفواصل بكل أشكالها حدود كلمات؛ الأحرف
     * والأرقام والعلامات المركَّبة تبقى.
     *
     * إدراج \p{M} ليس تفصيلاً: في الديفاناغاري والبنغالية والتاميلية
     * والتايلندية، علامات الحركات (ि ी ् ...) من فئة Mark لا Letter.
     * بدونها يقسّم التعبير "हिन्दी" إلى ["ह","न","द"] — أي يمحو الكلمة
     * ويترك هيكلاً ساكناً لا يطابق شيئاً.
     *
     * @return string[]
     */
    private static function words(string $run): array
    {
        $parts = preg_split('/[^\p{L}\p{N}\p{M}]+/u', $run, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        return array_values(array_filter(
            $parts,
            static fn (string $word): bool => mb_strlen($word, 'UTF-8') <= self::MAX_TOKEN_LENGTH
        ));
    }

    /**
     * n-grams على مستوى الحرف لمقطع بلا مسافات.
     *
     * مقطع بحرف واحد يُعاد كما هو: "书" استعلام صالح ولا يمكن تحويله
     * إلى bigram، وإسقاطه يعني فقدان البحث بحرف واحد كلياً.
     *
     * @return string[]
     */
    private static function ngrams(string $run): array
    {
        $chars = preg_split('/\s+/u', $run, -1, PREG_SPLIT_NO_EMPTY);
        $grams = [];

        foreach ($chars === false ? [] : $chars as $chunk) {
            $letters = mb_str_split($chunk, 1, 'UTF-8');
            $count = count($letters);

            if ($count < self::NGRAM_SIZE) {
                $grams[] = $chunk;

                continue;
            }

            for ($i = 0; $i + self::NGRAM_SIZE <= $count; $i++) {
                $grams[] = implode('', array_slice($letters, $i, self::NGRAM_SIZE));
            }
        }

        return $grams;
    }
}
