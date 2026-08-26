<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

/**
 * IntentTargets — أنواع المحتوى التي تخدم كل نيّة.
 *
 * ─── العلّة التي يعالجها هذا الصنف ──────────────────────────────────
 *
 * كانت هذه الخريطة منسوخة في ثلاثة مواضع، وقد افترقت الثلاثة فعلاً:
 *
 *   IntentDetector          buy / repair / compare / learn
 *   SearchResultRanker      buy / repair / compare / learn + product/article/service
 *   EloquentSearchRepository            product / article / service   ← فقط
 *
 * وبما أن الكاشف يُخرج "buy" بينما مفاتيح المستودع "product"، فإن
 * البحث في الخريطة يفشل دائماً فيعود مصفوفة فارغة. النتيجة أن فلترة
 * النية في SQL كانت كوداً ميتاً لم يُنفَّذ قطّ.
 *
 * وقد كان ذلك من حسن الحظ: لو تطابقت المفاتيح لصار الفلتر يعمل على
 * عمود data_type_slug الذي لم يكن يُملأ أصلاً — فكان كل استعلام يحمل
 * إشارة نية ("سعر"، "كيف"، "أفضل") سيعيد صفر نتائج.
 *
 * الخريطة هنا مصدر واحد لا ثالث له، والمفاتيح هي مفاتيح الكاشف نفسها.
 *
 * ─── ملاحظة للتشغيل ─────────────────────────────────────────────────
 *
 * الـ slugs أدناه شائعة لا شاملة. المشروع الذي يسمّي نوع محتواه
 * "shop-items" لن يستفيد من ترجيح النية حتى يُضاف الاسم هنا. وأثر
 * ذلك محدود بحكم التصميم: النية ترجّح ولا تفلتر، فالنتائج تظهر
 * بترتيب أقلّ إحكاماً لا أنها تختفي.
 */
final class IntentTargets
{
    /**
     * @var array<string, string[]>
     */
    private const MAP = [
        'buy' => ['products', 'product', 'items', 'item', 'goods', 'shop', 'store', 'منتجات', 'منتج'],
        'repair' => ['services', 'service', 'booking', 'bookings', 'appointments', 'support', 'خدمات', 'خدمة'],
        'compare' => ['articles', 'article', 'posts', 'post', 'blog', 'reviews', 'products', 'product', 'مقالات'],
        'learn' => ['articles', 'article', 'posts', 'post', 'blog', 'news', 'guides', 'docs', 'مقالات', 'أخبار'],
        'general' => [],
    ];

    /**
     * @return string[]
     */
    public static function slugsFor(string $intent): array
    {
        return self::MAP[$intent] ?? [];
    }

    public static function has(string $intent): bool
    {
        return self::slugsFor($intent) !== [];
    }
}
