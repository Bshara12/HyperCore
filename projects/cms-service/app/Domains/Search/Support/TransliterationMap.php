<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * TransliterationMap — جسر عربي ↔ إنجليزي (Cross-Language Terms)
 *
 * المشكلة التي يحلّها:
 *   المنتج مُسجَّل بالعربي كـ "آيفون 15 برو ماكس" وبالإنجليزي "iPhone 15 Pro Max".
 *   البحث بـ "ايفون" في index اللغة ar كان يفشل لأن:
 *     1. ArabicQueryNormalizer كان *يستبدل* "ايفون" بـ "iphone"
 *     2. ثم الـ repository يُقيّد البحث بـ si.language = 'ar'
 *     3. صفوف الـ ar لا تحتوي "iphone" اللاتينية → صفر نتائج
 *
 * الحل: الترجمة صارت **إضافية (additive) لا استبدالية (destructive)**.
 *   - على جانب الـ query: "ايفون" → مجموعة OR واحدة "+(ايفون* iphone*)"
 *   - على جانب الـ index: search_text يحتوي الشكلين معاً
 *   → أي جانب طابق، النتيجة تظهر، وبدون أي fallback من ar إلى en.
 *
 * المفاتيح مُخزَّنة **مُطبَّعة** (ArabicTextNormalizer::normalizeToken)
 * حتى تتطابق آيفون/أيفون/ايفون على مفتاح واحد.
 */
final class TransliterationMap
{
    /** الحد الأقصى للبدائل لكل token — لمنع boolean queries ضخمة */
    public const MAX_VARIANTS_PER_TOKEN = 3;

    /**
     * عربي → إنجليزي.
     * الاتجاه المعاكس يُبنى تلقائياً في index().
     *
     * @var array<string, string[]>
     */
    private const AR_TO_EN = [
        // ─── هواتف وماركات ─────────────────────────────────────────
        'ايفون'    => ['iphone'],
        'ابل'      => ['apple'],
        'سامسونج'  => ['samsung'],
        'سامسونغ'  => ['samsung'],
        'جالكسي'   => ['galaxy'],
        'جوال'     => ['phone', 'mobile'],
        'هاتف'     => ['phone', 'mobile'],
        'موبايل'   => ['mobile', 'phone'],
        'نوكيا'    => ['nokia'],
        'هواوي'    => ['huawei'],
        'شاومي'    => ['xiaomi'],
        'اوبو'     => ['oppo'],
        'ريلمي'    => ['realme'],
        'بيكسل'    => ['pixel'],
        'جوجل'     => ['google'],
        'اندرويد'  => ['android'],
        'برو'      => ['pro'],
        'ماكس'     => ['max'],
        'بلس'      => ['plus'],
        'الترا'    => ['ultra'],
        'ميني'     => ['mini'],

        // ─── أجهزة ─────────────────────────────────────────────────
        'لابتوب'   => ['laptop'],
        'حاسوب'    => ['computer', 'pc'],
        'كمبيوتر'  => ['computer', 'pc'],
        'ماك'      => ['mac'],
        'ماكبوك'   => ['macbook'],
        'ايباد'    => ['ipad'],
        'تابلت'    => ['tablet'],
        'لوحي'     => ['tablet'],
        'شاشه'     => ['screen', 'display'],
        'تلفزيون'  => ['tv', 'television'],
        'تلفاز'    => ['tv', 'television'],
        'كاميرا'   => ['camera'],
        'ساعه'     => ['watch'],
        'سماعه'    => ['headphone', 'earphone'],
        'سماعات'   => ['headphones', 'earphones'],
        'ايربودز'  => ['airpods'],
        'شاحن'     => ['charger'],
        'بطاريه'   => ['battery'],
        'كفر'      => ['case', 'cover'],
        'غطاء'     => ['cover', 'case'],
        'طابعه'    => ['printer'],
        'راوتر'    => ['router'],

        // ─── تجارة ─────────────────────────────────────────────────
        'سعر'      => ['price'],
        'اسعار'    => ['prices', 'price'],
        'تكلفه'    => ['cost', 'price'],
        'ثمن'      => ['price'],
        'شراء'     => ['buy', 'purchase'],
        'اشتري'    => ['buy'],
        'رخيص'     => ['cheap', 'affordable'],
        'ارخص'     => ['cheap', 'affordable'],
        'غالي'     => ['expensive'],
        'خصم'      => ['discount', 'sale'],
        'عرض'      => ['offer', 'deal'],
        'عروض'     => ['offers', 'deals'],
        'تخفيض'    => ['discount', 'sale'],
        'متجر'     => ['store', 'shop'],
        'توصيل'    => ['delivery', 'shipping'],
        'شحن'      => ['shipping', 'delivery'],
        'مجاني'    => ['free'],
        'تقسيط'    => ['installment'],
        'ضمان'     => ['warranty'],

        // ─── محتوى وخدمات ──────────────────────────────────────────
        'مقال'     => ['article', 'post'],
        'مقالات'   => ['articles', 'posts'],
        'اخبار'    => ['news'],
        'دليل'     => ['guide'],
        'شرح'      => ['tutorial', 'guide'],
        'مراجعه'   => ['review'],
        'تقييم'    => ['rating', 'review'],
        'خدمه'     => ['service'],
        'خدمات'    => ['services'],
        'حجز'      => ['booking', 'reservation'],
        'صيانه'    => ['repair', 'maintenance'],
        'تصليح'    => ['repair', 'fix'],
        'منتج'     => ['product', 'item'],
        'منتجات'   => ['products', 'items'],
    ];

    /**
     * الفهرس ثنائي الاتجاه — يُبنى مرة واحدة لكل عملية.
     *
     * @var array<string, string[]>|null
     */
    private static ?array $index = null;

    // ─────────────────────────────────────────────────────────────────

    /**
     * البدائل عبر-اللغوية لـ token واحد (لا تتضمن الـ token نفسه).
     *
     * variantsFor('آيفون')  → ['iphone']
     * variantsFor('iphone') → ['ايفون']
     *
     * @return string[]
     */
    public static function variantsFor(string $token): array
    {
        $key = ArabicTextNormalizer::normalizeToken($token);

        if ($key === '') {
            return [];
        }

        $variants = self::index()[$key] ?? [];

        return array_slice(
            array_values(array_filter($variants, static fn ($v) => $v !== $key)),
            0,
            self::MAX_VARIANTS_PER_TOKEN
        );
    }

    public static function has(string $token): bool
    {
        return self::variantsFor($token) !== [];
    }

    /**
     * توسيع قائمة tokens بالبدائل عبر-اللغوية (إضافة لا استبدال).
     *
     * @param  string[]  $tokens
     * @return string[]  البدائل الجديدة فقط (بدون الـ tokens الأصلية)
     */
    public static function expandTokens(array $tokens): array
    {
        $known = [];
        foreach ($tokens as $token) {
            $known[ArabicTextNormalizer::normalizeToken($token)] = true;
        }

        $extra = [];
        foreach ($tokens as $token) {
            foreach (self::variantsFor($token) as $variant) {
                if (! isset($known[$variant]) && ! isset($extra[$variant])) {
                    $extra[$variant] = true;
                }
            }
        }

        return array_keys($extra);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string[]>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::AR_TO_EN as $arabic => $englishTerms) {
            $arKey = ArabicTextNormalizer::normalizeToken($arabic);

            foreach ($englishTerms as $english) {
                $enKey = ArabicTextNormalizer::normalizeToken($english);

                // ar → en
                $index[$arKey] ??= [];
                if (! in_array($enKey, $index[$arKey], true)) {
                    $index[$arKey][] = $enKey;
                }

                // en → ar (الاتجاه المعاكس يُبنى تلقائياً)
                $index[$enKey] ??= [];
                if (! in_array($arKey, $index[$enKey], true)) {
                    $index[$enKey][] = $arKey;
                }
            }
        }

        return self::$index = $index;
    }
}
