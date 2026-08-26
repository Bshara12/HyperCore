<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Text;

/**
 * UnicodeScript — كشف نظام الكتابة (script) لأي نص، لأي لغة.
 *
 * لماذا script وليس language؟
 *   كشف اللغة الفعلي (عربي مقابل فارسي مقابل أردو) يحتاج نماذج إحصائية
 *   وبيانات تدريب. لكن قرارات محرك البحث لا تحتاج اللغة — تحتاج الـ script:
 *
 *     - كيف نقسّم النص إلى وحدات؟   (مسافات مقابل n-grams)
 *     - كيف نطبّع الأحرف؟            (أي علامات نحذف)
 *     - أي فهرس FULLTEXT نستهدف؟     (default parser مقابل ngram parser)
 *
 *   الـ script يجيب على الثلاثة بشكل حتمي وبدون نموذج، ويغطي كل لغات
 *   العالم تلقائياً: لغة جديدة بنفس الـ script تعمل بلا أي تعديل كود.
 *
 * المصدر: Unicode 15 Scripts.txt (النطاقات الأساسية لكل script).
 */
final class UnicodeScript
{
    public const LATIN = 'Latn';

    public const ARABIC = 'Arab';

    public const HEBREW = 'Hebr';

    public const CYRILLIC = 'Cyrl';

    public const GREEK = 'Grek';

    public const HAN = 'Hani';

    public const HIRAGANA = 'Hira';

    public const KATAKANA = 'Kana';

    public const HANGUL = 'Hang';

    public const THAI = 'Thai';

    public const LAO = 'Laoo';

    public const KHMER = 'Khmr';

    public const MYANMAR = 'Mymr';

    public const TIBETAN = 'Tibt';

    public const DEVANAGARI = 'Deva';

    public const BENGALI = 'Beng';

    public const GURMUKHI = 'Guru';

    public const GUJARATI = 'Gujr';

    public const ORIYA = 'Orya';

    public const TAMIL = 'Taml';

    public const TELUGU = 'Telu';

    public const KANNADA = 'Knda';

    public const MALAYALAM = 'Mlym';

    public const SINHALA = 'Sinh';

    public const ETHIOPIC = 'Ethi';

    public const GEORGIAN = 'Geor';

    public const ARMENIAN = 'Armn';

    public const THAANA = 'Thaa';

    public const COMMON = 'Zyyy';

    /**
     * نطاقات الـ code points لكل script.
     *
     * @var array<string, array<int, array{0:int,1:int}>>
     */
    private const RANGES = [
        self::LATIN => [
            [0x0041, 0x005A], [0x0061, 0x007A], [0x00AA, 0x00AA], [0x00BA, 0x00BA],
            [0x00C0, 0x00D6], [0x00D8, 0x00F6], [0x00F8, 0x02B8], [0x1E00, 0x1EFF],
            [0x2C60, 0x2C7F], [0xA720, 0xA7FF], [0xFB00, 0xFB06], [0xFF21, 0xFF3A],
            [0xFF41, 0xFF5A],
        ],
        self::GREEK => [
            [0x0370, 0x0373], [0x0375, 0x0377], [0x037A, 0x037D], [0x037F, 0x037F],
            [0x0384, 0x0384], [0x0386, 0x0386], [0x0388, 0x03FF], [0x1F00, 0x1FFE],
        ],
        self::CYRILLIC => [
            [0x0400, 0x0484], [0x0487, 0x052F], [0x1C80, 0x1C8A],
            [0x2DE0, 0x2DFF], [0xA640, 0xA69F],
        ],
        self::ARMENIAN => [[0x0531, 0x058A], [0xFB13, 0xFB17]],
        self::HEBREW => [[0x0591, 0x05F4], [0xFB1D, 0xFB4F]],
        self::ARABIC => [
            [0x0600, 0x0604], [0x0606, 0x060B], [0x060D, 0x061A], [0x061E, 0x061E],
            [0x0620, 0x063F], [0x0641, 0x064A], [0x0656, 0x066F], [0x0671, 0x06DC],
            [0x06DE, 0x06FF], [0x0750, 0x077F], [0x0870, 0x088E], [0x08A0, 0x08E1],
            [0x08E3, 0x08FF], [0xFB50, 0xFBC1], [0xFBD3, 0xFD3D], [0xFD50, 0xFDFC],
            [0xFE70, 0xFEFC],
        ],
        self::THAANA => [[0x0780, 0x07B1]],
        self::DEVANAGARI => [[0x0900, 0x0950], [0x0953, 0x097F], [0xA8E0, 0xA8FF]],
        self::BENGALI => [[0x0980, 0x09FE]],
        self::GURMUKHI => [[0x0A01, 0x0A76]],
        self::GUJARATI => [[0x0A81, 0x0AFF]],
        self::ORIYA => [[0x0B01, 0x0B77]],
        self::TAMIL => [[0x0B82, 0x0BFA]],
        self::TELUGU => [[0x0C00, 0x0C7F]],
        self::KANNADA => [[0x0C80, 0x0CF3]],
        self::MALAYALAM => [[0x0D00, 0x0D7F]],
        self::SINHALA => [[0x0D81, 0x0DF4]],
        self::THAI => [[0x0E01, 0x0E3A], [0x0E40, 0x0E5B]],
        self::LAO => [[0x0E81, 0x0EDF]],
        self::TIBETAN => [[0x0F00, 0x0FDA]],
        self::MYANMAR => [[0x1000, 0x109F], [0xA9E0, 0xA9FE], [0xAA60, 0xAA7F]],
        self::GEORGIAN => [[0x10A0, 0x10FF], [0x1C90, 0x1CBF], [0x2D00, 0x2D2F]],
        self::HANGUL => [
            [0x1100, 0x11FF], [0x3131, 0x318E], [0xA960, 0xA97C],
            [0xAC00, 0xD7A3], [0xD7B0, 0xD7FB], [0xFFA1, 0xFFDC],
        ],
        self::ETHIOPIC => [[0x1200, 0x139F], [0x2D80, 0x2DDE], [0xAB01, 0xAB2E]],
        self::KHMER => [[0x1780, 0x17F9], [0x19E0, 0x19FF]],
        self::HIRAGANA => [[0x3041, 0x309F], [0x1B001, 0x1B11F]],
        self::KATAKANA => [[0x30A1, 0x30FF], [0x31F0, 0x31FF], [0xFF66, 0xFF9D]],
        self::HAN => [
            [0x2E80, 0x2EF3], [0x3005, 0x3005], [0x3007, 0x3007], [0x3021, 0x3029],
            [0x3400, 0x4DBF], [0x4E00, 0x9FFF], [0xF900, 0xFA6D], [0xFA70, 0xFAD9],
            [0x20000, 0x2A6DF], [0x2A700, 0x2EBE0], [0x2F800, 0x2FA1D],
        ],
    ];

    /**
     * Scripts تُكتب بلا فواصل بين الكلمات (scriptio continua).
     *
     * هذه هي بالضبط الـ scripts التي يعجز عنها الـ parser الافتراضي في
     * MySQL FULLTEXT — لأنه يقسّم على المسافات والترقيم فقط، فيرى الجملة
     * الصينية كلها "كلمة" واحدة. تُوجَّه إلى فهرس الـ ngram بدلاً منه.
     *
     * @var array<string, true>
     */
    private const UNSEGMENTED = [
        self::HAN => true,
        self::HIRAGANA => true,
        self::KATAKANA => true,
        self::THAI => true,
        self::LAO => true,
        self::KHMER => true,
        self::MYANMAR => true,
        self::TIBETAN => true,
    ];

    /**
     * Scripts تُكتب من اليمين لليسار.
     *
     * @var array<string, true>
     */
    private const RTL = [
        self::ARABIC => true,
        self::HEBREW => true,
        self::THAANA => true,
    ];

    /**
     * script حرف واحد. الأحرف غير المصنّفة (أرقام، ترقيم، رموز) => COMMON.
     */
    public static function ofCodepoint(int $cp): string
    {
        foreach (self::RANGES as $script => $ranges) {
            foreach ($ranges as [$start, $end]) {
                if ($cp >= $start && $cp <= $end) {
                    return $script;
                }
            }
        }

        return self::COMMON;
    }

    /**
     * توزيع الـ scripts في نص، كنِسَب من الأحرف المصنَّفة.
     *
     * الأرقام والترقيم والمسافات مستبعدة من المقام: "iPhone 15" يجب أن
     * يُقرأ لاتينياً 100%، لا 60% لاتيني و40% common.
     *
     * @return array<string, float> script => نسبة (0.0–1.0)، تنازلياً
     */
    public static function profile(string $text): array
    {
        $counts = [];
        $total = 0;

        foreach (self::codepoints($text) as $cp) {
            $script = self::ofCodepoint($cp);

            if ($script === self::COMMON) {
                continue;
            }

            $counts[$script] = ($counts[$script] ?? 0) + 1;
            $total++;
        }

        if ($total === 0) {
            return [];
        }

        $profile = [];
        foreach ($counts as $script => $count) {
            $profile[$script] = round($count / $total, 4);
        }

        arsort($profile);

        return $profile;
    }

    /**
     * الـ script المهيمن، أو COMMON إذا لم يوجد حرف مصنَّف (استعلام أرقام بحت).
     */
    public static function dominant(string $text): string
    {
        $profile = self::profile($text);

        return $profile === [] ? self::COMMON : (string) array_key_first($profile);
    }

    /**
     * كل script تجاوز عتبة الحضور. يُستخدم للاستعلامات المختلطة
     * ("iphone سعر" => [Latn, Arab]) حيث لا يكفي المهيمن وحده.
     *
     * @return string[]
     */
    public static function present(string $text, float $threshold = 0.15): array
    {
        return array_keys(array_filter(
            self::profile($text),
            static fn (float $ratio): bool => $ratio >= $threshold
        ));
    }

    /**
     * هل النص يخلط أكثر من script بنسب معتبرة؟
     */
    public static function isMixed(string $text, float $threshold = 0.15): bool
    {
        return count(self::present($text, $threshold)) > 1;
    }

    /**
     * هل هذا الـ script بلا فواصل كلمات؟ (يحتاج تقسيم n-gram)
     */
    public static function isUnsegmented(string $script): bool
    {
        return isset(self::UNSEGMENTED[$script]);
    }

    /**
     * هل النص يحتوي أي script بلا فواصل كلمات؟
     *
     * لا توجد عتبة هنا عمداً: كلمة صينية واحدة داخل جملة إنجليزية تظل
     * غير قابلة للفهرسة بالـ parser الافتراضي، فيجب توجيهها للـ ngram.
     */
    public static function needsNgram(string $text): bool
    {
        foreach (array_keys(self::profile($text)) as $script) {
            if (self::isUnsegmented($script)) {
                return true;
            }
        }

        return false;
    }

    public static function isRtl(string $script): bool
    {
        return isset(self::RTL[$script]);
    }

    /**
     * تفكيك نص UTF-8 إلى code points.
     *
     * @return \Generator<int, int>
     */
    public static function codepoints(string $text): \Generator
    {
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            yield mb_ord($char, 'UTF-8');
        }
    }
}
