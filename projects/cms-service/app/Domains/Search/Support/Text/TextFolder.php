<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Text;

use Normalizer;

/**
 * TextFolder — تطبيع نصّي محايد لغوياً.
 *
 * المشكلة التي يحلّها:
 *   الفهرسة والاستعلام يجب أن يمرّا بنفس التحويل بالضبط، وإلا فشلت
 *   المطابقة. النسخة السابقة طبّعت العربية فقط وداخل ArabicQueryNormalizer
 *   على جانب الاستعلام وحده — فكان "قَهْوَة" في المحتوى لا يطابق "قهوه"
 *   في الاستعلام، و"café" لا يطابق "cafe"، و"ＩＰＨＯＮＥ" لا يطابق شيئاً.
 *
 * القاعدة الوحيدة هنا: fold() هي الدالة الرسمية الوحيدة، وتُستدعى على
 * جانبَي الفهرسة والاستعلام. أي شيء لا يمرّ بها هو خطأ.
 *
 * خطوات التطبيع، بالترتيب:
 *   1. NFKC          — توحيد أشكال العرض (ＩＰＨＯＮＥ→IPHONE، ﬁ→fi، ①→1)
 *   2. lowercase     — طيّ حالة الأحرف بوعي Unicode (İ، Σ، Ä)
 *   3. حذف العلامات  — للـ scripts التي تكون فيها العلامات اختيارية فقط
 *   4. توحيد الحروف  — أشكال إملائية متكافئة (أ/إ/آ→ا، ة→ه، ς→σ)
 *   5. توحيد الأرقام — كل أرقام Unicode العشرية → ASCII (٢٠٢٠→2020)
 *   6. طيّ الكانا     — カタカナ→かたかな ليطابق الشكلان بعضهما
 *   7. المسافات      — تقليص وتشذيب
 */
final class TextFolder
{
    /**
     * علامات تشكيل اختيارية: حذفها لا يغيّر هوية الكلمة.
     *
     * هذه القائمة انتقائية عمداً. حذف كل علامات Unicode المركَّبة يدمّر
     * الديفاناغاري والتايلندية والخميرية، حيث علامات الحركات حروف كاملة
     * المعنى — "क" و"का" كلمتان مختلفتان. النطاقات أدناه فقط هي التي
     * تكون فيها العلامة زخرفية أو اختيارية في الكتابة الفعلية.
     *
     * @var array<int, array{0:int,1:int}>
     */
    private const OPTIONAL_MARKS = [
        [0x0300, 0x036F],   // Combining Diacritical Marks (لاتيني/يوناني/كيريلي)
        [0x0483, 0x0489],   // Cyrillic combining
        [0x0591, 0x05BD],   // Hebrew cantillation + niqqud
        [0x05BF, 0x05BF],
        [0x05C1, 0x05C2],
        [0x05C4, 0x05C5],
        [0x05C7, 0x05C7],
        [0x0610, 0x061A],   // Arabic honorifics
        [0x064B, 0x065F],   // Arabic harakat (fatha/damma/kasra/shadda/sukun)
        [0x0670, 0x0670],   // Arabic superscript alef
        [0x06D6, 0x06ED],   // Quranic annotation marks
        [0x08D3, 0x08FF],   // Arabic Extended-A marks
        [0x1AB0, 0x1AFF],   // Combining Diacritical Marks Extended
        [0x1DC0, 0x1DFF],   // Combining Diacritical Marks Supplement
        [0x20D0, 0x20F0],   // Combining Diacritical Marks for Symbols
        [0xFE20, 0xFE2F],   // Combining Half Marks
    ];

    /**
     * أشكال إملائية متكافئة للبحث.
     *
     * كل مدخل هنا يمثّل تنويعاً يكتبه الناس بالتبادل ويقصدون به الشيء
     * نفسه. هذا ليس ترجمة ولا اشتقاقاً — بل توحيد شكل الحرف فقط.
     *
     * @var array<string, string>
     */
    private const LETTER_FOLDS = [
        // ─── عربي: تنويعات الألف والياء والتاء المربوطة ───────────────
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
        'ـ' => '',   // tatweel: مطّة زخرفية بلا معنى

        // ─── فارسي/أردو → الأشكال العربية ─────────────────────────────
        'ک' => 'ك', 'ی' => 'ي', 'ې' => 'ي', 'ۀ' => 'ه', 'ہ' => 'ه',
        'ھ' => 'ه', 'ڪ' => 'ك', 'ٹ' => 'ت', 'ڈ' => 'د', 'ڑ' => 'ر',

        // ─── عبري: الأشكال النهائية ───────────────────────────────────
        'ך' => 'כ', 'ם' => 'מ', 'ן' => 'נ', 'ף' => 'פ', 'ץ' => 'צ',

        // ─── يوناني: السيغما النهائية ─────────────────────────────────
        'ς' => 'σ',

        // ─── لاتيني: أحرف مركّبة لا يفكّكها NFD ───────────────────────
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o',
        'đ' => 'd', 'ð' => 'd', 'þ' => 'th', 'ł' => 'l', 'ħ' => 'h',
        'ı' => 'i', 'ŋ' => 'n', 'ſ' => 's',
    ];

    /**
     * بدايات كتل الأرقام العشرية في Unicode.
     *
     * كل كتلة عشرة أحرف متتالية تمثّل 0..9، فيكفي معرفة بداية الكتلة
     * لتحويل أي رقم فيها: cp - start = القيمة.
     *
     * @var int[]
     */
    private const DIGIT_BLOCK_STARTS = [
        0x0660, // Arabic-Indic        ٠١٢٣٤٥٦٧٨٩
        0x06F0, // Extended Arabic-Indic (فارسي) ۰۱۲۳۴۵۶۷۸۹
        0x07C0, // Nko
        0x0966, // Devanagari
        0x09E6, // Bengali
        0x0A66, // Gurmukhi
        0x0AE6, // Gujarati
        0x0B66, // Oriya
        0x0BE6, // Tamil
        0x0C66, // Telugu
        0x0CE6, // Kannada
        0x0D66, // Malayalam
        0x0DE6, // Sinhala
        0x0E50, // Thai
        0x0ED0, // Lao
        0x0F20, // Tibetan
        0x1040, // Myanmar
        0x17E0, // Khmer
        0x1810, // Mongolian
        0xFF10, // Fullwidth  ０１２３４５６７８９
    ];

    /** طيّ الكاتاكانا إلى هيراغانا: الكتلتان متوازيتان بإزاحة ثابتة. */
    private const KATAKANA_START = 0x30A1;

    private const KATAKANA_END = 0x30F6;

    private const KANA_OFFSET = 0x60;

    /**
     * الدالة الرسمية الوحيدة للتطبيع. تُستدعى على جانبَي الفهرسة والاستعلام.
     */
    public static function fold(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $text = self::toNfkc($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = self::stripOptionalMarks($text);
        $text = strtr($text, self::LETTER_FOLDS);
        $text = self::foldDigitsAndKana($text);

        return self::collapseWhitespace($text);
    }

    /**
     * طيّ خفيف للعرض: يوحّد الشكل دون حذف العلامات أو تغيير الحروف.
     *
     * يُستخدم عند تخزين النص الأصلي للعرض والتظليل، حيث يجب أن يبقى
     * "قَهْوَة" مقروءاً كما كتبه صاحب المحتوى.
     */
    public static function foldForDisplay(string $text): string
    {
        return self::collapseWhitespace(self::toNfkc($text));
    }

    /**
     * هل النصّان متكافئان بعد التطبيع؟
     */
    public static function equivalent(string $a, string $b): bool
    {
        return self::fold($a) === self::fold($b);
    }

    // ─────────────────────────────────────────────────────────────────

    private static function toNfkc(string $text): string
    {
        if (! class_exists(Normalizer::class)) {
            return $text;
        }

        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);

        return $normalized === false ? $text : $normalized;
    }

    /**
     * حذف علامات التشكيل الاختيارية.
     *
     * التفكيك إلى NFD أولاً ضروري: "é" المركَّبة (U+00E9) لا تحتوي علامة
     * منفصلة أصلاً، فلا يوجد ما يُحذف. NFD تحوّلها إلى "e" + U+0301،
     * فتصبح العلامة قابلة للحذف. ثم NFC لإعادة تركيب ما بقي.
     */
    private static function stripOptionalMarks(string $text): string
    {
        $decomposed = class_exists(Normalizer::class)
            ? Normalizer::normalize($text, Normalizer::FORM_D)
            : $text;

        if ($decomposed === false) {
            $decomposed = $text;
        }

        $out = '';

        foreach (mb_str_split($decomposed, 1, 'UTF-8') as $char) {
            if (self::isOptionalMark(mb_ord($char, 'UTF-8'))) {
                continue;
            }

            $out .= $char;
        }

        if (! class_exists(Normalizer::class)) {
            return $out;
        }

        $recomposed = Normalizer::normalize($out, Normalizer::FORM_C);

        return $recomposed === false ? $out : $recomposed;
    }

    private static function isOptionalMark(int $cp): bool
    {
        foreach (self::OPTIONAL_MARKS as [$start, $end]) {
            if ($cp >= $start && $cp <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * توحيد الأرقام إلى ASCII وطيّ الكاتاكانا إلى هيراغانا في مرور واحد.
     */
    private static function foldDigitsAndKana(string $text): string
    {
        $out = '';

        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            $digit = self::asAsciiDigit($cp);

            if ($digit !== null) {
                $out .= $digit;

                continue;
            }

            if ($cp >= self::KATAKANA_START && $cp <= self::KATAKANA_END) {
                $out .= mb_chr($cp - self::KANA_OFFSET, 'UTF-8');

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    private static function asAsciiDigit(int $cp): ?string
    {
        foreach (self::DIGIT_BLOCK_STARTS as $start) {
            if ($cp >= $start && $cp <= $start + 9) {
                return (string) ($cp - $start);
            }
        }

        return null;
    }

    private static function collapseWhitespace(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text);

        return trim($collapsed ?? $text);
    }
}
