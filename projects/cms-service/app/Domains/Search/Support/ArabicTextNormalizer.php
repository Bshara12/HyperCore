<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * ArabicTextNormalizer — المُطبِّع الموحَّد (Single Source of Truth)
 *
 * المشكلة التي يحلّها:
 *   قبل هذا الـ class كان الـ query side يُطبِّع النص (آ → ا, إزالة تشكيل)
 *   بينما الـ index side يُخزِّن النص الخام كما هو.
 *   النتيجة: عنوان "آيفون 15 برو ماكس" يُفهرس كـ token "آيفون"
 *   والبحث عن "ايفون" يُنتج token "ايفون" → لا تطابق أبداً.
 *   (asymmetric analyzer — أشهر خطأ في أنظمة IR العربية)
 *
 * القاعدة الذهبية:
 *   أي نص يدخل الـ FULLTEXT index يجب أن يمرّ من هنا،
 *   وأي query يُطابَق ضده يجب أن يمرّ من هنا أيضاً — بنفس الدالة.
 *
 * ما يفعله (متوافق مع Lucene ArabicNormalizationFilter + إضافات):
 *   1. أشكال الألف     → ا   (أ إ آ ٱ ٲ ٳ)
 *   2. الياء والألف المقصورة والهمزة على الياء → ي (ى ئ ی ې)
 *   3. الهمزة على الواو → و   (ؤ)
 *   4. التاء المربوطة   → ه   (ة)
 *   5. الكاف الفارسية   → ك   (ک ڪ)
 *   6. إزالة التشكيل والتطويل والعلامات القرآنية
 *   7. الأرقام العربية-الهندية والفارسية → أرقام ASCII
 *   8. إزالة zero-width characters (تُفسد الـ tokenizer)
 *   9. توحيد المسافات + lowercase (للنصوص اللاتينية)
 *
 * ما لا يفعله (مقصود):
 *   لا يمسّ علامات BOOLEAN MODE (+ - * " ( ) ~ <>) لأن نفس الدالة
 *   تُستخدم لتطبيع الـ boolean query قبل تمريره لـ MySQL.
 */
final class ArabicTextNormalizer
{
    /** طيّ الحروف — مفتاح: الشكل المتغيّر، قيمة: الشكل الموحَّد */
    private const LETTER_FOLD = [
        // ─── أشكال الألف ───────────────────────────────────────────
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا', 'ٵ' => 'ا',
        // ─── الياء ─────────────────────────────────────────────────
        'ى' => 'ي', 'ئ' => 'ي', 'ی' => 'ي', 'ې' => 'ي', 'ۍ' => 'ي', 'ﻯ' => 'ي',
        // ─── الواو ─────────────────────────────────────────────────
        'ؤ' => 'و', 'ۇ' => 'و', 'ۋ' => 'و',
        // ─── التاء المربوطة ────────────────────────────────────────
        'ة' => 'ه',
        // ─── الكاف ─────────────────────────────────────────────────
        'ک' => 'ك', 'ڪ' => 'ك', 'ګ' => 'ك',
        // ─── حروف أخرى شائعة في الكيبوردات الفارسية/الأردية ────────
        'ھ' => 'ه', 'ہ' => 'ه',
    ];

    /**
     * الخريطة المعاكسة للطيّ — تُستخدم لبناء regex للـ highlighting
     * 'ا' => ['ا','أ','إ','آ',...]
     *
     * المفاتيح array-key لا string: PHP يُحوّل مفاتيح الأرقام
     * ('0'..'9') إلى int تلقائياً.
     *
     * @var array<array-key, string[]>|null
     */
    private static ?array $unfoldCache = null;

    /** التشكيل + العلامات القرآنية + التطويل */
    private const DIACRITICS_PATTERN =
        '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    /** zero-width + علامات الاتجاه — تُفسد tokenization الـ FULLTEXT */
    private const INVISIBLE_PATTERN =
        '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{061C}\x{FEFF}]/u';

    private const DIGIT_FOLD = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    // ─────────────────────────────────────────────────────────────────

    /**
     * التطبيع الكامل — تُستدعى في الفهرسة والبحث على حد سواء.
     *
     * idempotent: normalize(normalize($x)) === normalize($x)
     */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = preg_replace(self::INVISIBLE_PATTERN, '', $text) ?? $text;
        $text = preg_replace(self::DIACRITICS_PATTERN, '', $text) ?? $text;

        $text = strtr($text, self::LETTER_FOLD);
        $text = strtr($text, self::DIGIT_FOLD);

        $text = mb_strtolower($text, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * تطبيع token واحد (بدون مسافات داخلية)
     */
    public static function normalizeToken(?string $token): string
    {
        return str_replace(' ', '', self::normalize($token));
    }

    /**
     * تقسيم نص مُطبَّع إلى tokens قابلة للفهرسة/البحث
     *
     * @return string[]
     */
    public static function tokenize(?string $text, int $minLength = 2): array
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            static fn ($t) => mb_strlen($t, 'UTF-8') >= $minLength
        )));
    }

    /**
     * هل النص يحتوي حروفاً عربية؟
     */
    public static function hasArabic(?string $text): bool
    {
        return $text !== null && preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }

    /**
     * بناء regex-fragment يُطابق كلمة مُطبَّعة داخل نص خام غير مُطبَّع.
     *
     * لماذا؟ الـ cleanWords صارت مُطبَّعة ("ايفون") لكن الـ title المعروض
     * للمستخدم خام ("آيفون 15"). للـ highlighting والـ snippet نحتاج
     * مُطابقة "متسامحة" مع التشكيل وأشكال الألف.
     *
     * ملاحظة: يُعيد fragment بدون delimiters — المُستدعي يُغلِّفه.
     */
    public static function looseRegex(string $normalizedWord): string
    {
        $unfold = self::unfoldMap();
        $chars  = preg_split('//u', $normalizedWord, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // يُسمح بتشكيل/تطويل/zero-width بين كل حرفين
        $filler = '[\x{064B}-\x{065F}\x{0640}\x{200B}-\x{200F}]*';

        $parts = [];
        foreach ($chars as $char) {
            $variants = $unfold[$char] ?? [$char];

            $parts[] = count($variants) === 1
                ? preg_quote($variants[0], '/')
                : '[' . implode('', array_map(
                    static fn ($v) => preg_quote($v, '/'),
                    $variants
                )) . ']';
        }

        return implode($filler, $parts);
    }

    /**
     * الخريطة المعاكسة: الحرف الموحَّد → كل أشكاله المحتملة
     *
     * @return array<array-key, string[]>
     */
    private static function unfoldMap(): array
    {
        if (self::$unfoldCache !== null) {
            return self::$unfoldCache;
        }

        $map = [];

        foreach (self::LETTER_FOLD as $variant => $canonical) {
            $map[$canonical] ??= [$canonical];
            $map[$canonical][] = $variant;
        }

        foreach (self::DIGIT_FOLD as $variant => $canonical) {
            $map[$canonical] ??= [$canonical];
            $map[$canonical][] = $variant;
        }

        foreach ($map as $canonical => $variants) {
            $map[$canonical] = array_values(array_unique($variants));
        }

        return self::$unfoldCache = $map;
    }
}
