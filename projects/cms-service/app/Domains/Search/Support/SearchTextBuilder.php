<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * SearchTextBuilder — يبني عمود search_text المُفهرس
 *
 * لماذا عمود منفصل بدل الفهرسة على (title, content) مباشرة؟
 *
 *   1. **تناظر المُحلِّل (analyzer symmetry)**: النص المُفهرس يجب أن يمرّ
 *      من نفس التطبيع الذي يمرّ منه الـ query. لا يمكن تطبيع title/content
 *      نفسيهما لأنهما يُعرضان للمستخدم كما كتبهما (بالتشكيل وبـ "آيفون").
 *
 *   2. **الـ meta كانت مهدورة**: الوسوم (tags) تُخزَّن في meta ولم تكن
 *      داخل الـ FULLTEXT index أبداً، رغم أنها تحتوي أدق كلمات البحث
 *      (مثال حقيقي من الـ seeder: meta.tags = "ايفون، ابل، جوال، سعر").
 *
 *   3. **المصطلحات عبر-اللغوية**: صف اللغة ar يحصل على مقابله اللاتيني
 *      (ايفون → iphone) وصف اللغة en يحصل على مقابله العربي، فيصير
 *      نظام الـ IR هو من يُطابق — بدون أي fallback من ar إلى en في العميل.
 *
 * MySQL InnoDB لا يدعم أوزاناً لكل عمود داخل FULLTEXT index واحد،
 * لذلك دمج title+content+meta في عمود واحد لا يُفقدنا شيئاً؛
 * ترجيح العنوان يتم في SearchResultRanker على النص الخام.
 */
final class SearchTextBuilder
{
    /** أقصى طول للنص المُفهرس — حماية من entries ضخمة */
    private const MAX_LENGTH = 60000;

    /** أقصى عدد بدائل عبر-لغوية تُضاف لكل صف */
    private const MAX_CROSS_LANGUAGE_TOKENS = 60;

    /** أقصى عمق للتنقيب داخل meta المتشعّبة */
    private const MAX_META_DEPTH = 4;

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<mixed>|string|null  $meta  مصفوفة أو JSON string
     */
    public function build(?string $title, ?string $content, array|string|null $meta = null): string
    {
        $raw = implode(' ', array_filter([
            (string) $title,
            (string) $content,
            $this->flattenMeta($meta),
        ], static fn ($part) => trim($part) !== ''));

        $normalized = ArabicTextNormalizer::normalize($raw);

        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized, 'UTF-8') > self::MAX_LENGTH) {
            $normalized = mb_substr($normalized, 0, self::MAX_LENGTH, 'UTF-8');
        }

        $crossLanguage = array_slice(
            TransliterationMap::expandTokens(ArabicTextNormalizer::tokenize($normalized)),
            0,
            self::MAX_CROSS_LANGUAGE_TOKENS
        );

        return $crossLanguage === []
            ? $normalized
            : $normalized . ' ' . implode(' ', $crossLanguage);
    }

    /**
     * بناء search_text من صف جدول search_indices (للـ backfill)
     *
     * @param  array<string, mixed>|object  $row
     */
    public function buildFromRow(array|object $row): string
    {
        $row = (array) $row;

        return $this->build(
            isset($row['title']) ? (string) $row['title'] : null,
            isset($row['content']) ? (string) $row['content'] : null,
            $row['meta'] ?? null,
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تسطيح meta (مصفوفة أو JSON) إلى نص واحد قابل للفهرسة.
     * المفاتيح تُتجاهل — القيم النصية/الرقمية فقط هي المفيدة للبحث.
     */
    private function flattenMeta(array|string|null $meta, int $depth = 0): string
    {
        if ($meta === null || $meta === '' || $depth > self::MAX_META_DEPTH) {
            return '';
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);

            // meta قد تكون نصاً عادياً غير JSON
            if (! is_array($decoded)) {
                return json_last_error() === JSON_ERROR_NONE && is_scalar($decoded)
                    ? (string) $decoded
                    : $meta;
            }

            $meta = $decoded;
        }

        $parts = [];

        foreach ($meta as $value) {
            if (is_array($value)) {
                $parts[] = $this->flattenMeta($value, $depth + 1);
            } elseif (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        }

        return implode(' ', array_filter($parts, static fn ($p) => trim($p) !== ''));
    }
}
