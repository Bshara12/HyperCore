<?php

namespace App\Domains\Search\Support;

class SynonymProvider
{
    /**
     * الحد الأقصى للمرادفات لكل كلمة
     * نحافظ عليه منخفضاً لتجنب queries ضخمة تُبطئ MySQL
     */
    private const MAX_SYNONYMS_PER_WORD = 2;

    /**
     * خريطة المرادفات - ثنائية الاتجاه
     *
     * القاعدة:
     *   - إذا أضفت "phone" → ["mobile"] يجب أن تضيف "mobile" → ["phone"] أيضاً
     *   - الكلمات بالـ lowercase دائماً
     *   - العربي والإنجليزي في نفس الخريطة
     *
     * @var array<string, string[]>
     */
    private const SYNONYM_MAP = [

        // ─── 📱 Mobile / Phone ───────────────────────────────────────
        'phone'      => ['mobile', 'cellphone'],
        'mobile'     => ['phone', 'cellphone'],
        'cellphone'  => ['phone', 'mobile'],
        'iphone'     => ['apple phone', 'ios phone'],
        'smartphone' => ['mobile', 'phone'],

        // ─── 💻 Tech / Devices ───────────────────────────────────────
        'laptop'     => ['notebook', 'computer'],
        'notebook'   => ['laptop', 'computer'],
        'computer'   => ['pc', 'desktop'],
        'pc'         => ['computer', 'desktop'],
        'tablet'     => ['ipad', 'device'],
        'ipad'       => ['tablet', 'apple tablet'],
        'tv'         => ['television', 'screen'],
        'television' => ['tv', 'screen'],
        'watch'      => ['smartwatch', 'wearable'],
        'smartwatch' => ['watch', 'wearable'],
        'headphone'  => ['earphone', 'headset'],
        'earphone'   => ['headphone', 'earbud'],
        'earbud'     => ['earphone', 'headphone'],
        'camera'     => ['dslr', 'photography'],

        // ─── 🛒 E-commerce ───────────────────────────────────────────
        'cheap'      => ['affordable', 'budget'],
        'affordable' => ['cheap', 'budget'],
        'offer'      => ['deal', 'discount'],
        'deal'       => ['offer', 'discount'],
        'discount'   => ['offer', 'sale'],
        'sale'       => ['discount', 'offer'],
        'buy'        => ['purchase', 'order'],
        'purchase'   => ['buy', 'order'],
        'price'      => ['cost', 'rate'],
        'cost'       => ['price', 'rate'],
        'shop'       => ['store', 'market'],
        'store'      => ['shop', 'market'],

        // ─── 📰 CMS / Content ────────────────────────────────────────
        'article'    => ['post', 'blog'],
        'post'       => ['article', 'blog'],
        'blog'       => ['article', 'post'],
        'news'       => ['article', 'update'],
        'tutorial'   => ['guide', 'howto'],
        'guide'      => ['tutorial', 'manual'],
        'manual'     => ['guide', 'documentation'],
        'docs'       => ['documentation', 'manual'],
        'documentation' => ['docs', 'manual'],
        'review'     => ['rating', 'feedback'],
        'rating'     => ['review', 'score'],
        'feedback'   => ['review', 'comment'],

        // ─── 🏷 Categories ───────────────────────────────────────────
        'category'   => ['section', 'group'],
        'section'    => ['category', 'part'],
        'product'    => ['item', 'goods'],
        'item'       => ['product', 'goods'],
        'service'    => ['solution', 'offering'],

        // ─── 🌍 Arabic - Mobile / Phone ──────────────────────────────
        'جوال'       => ['هاتف', 'موبايل'],
        'هاتف'       => ['جوال', 'موبايل'],
        'موبايل'     => ['جوال', 'هاتف'],
        'ايفون'      => ['ابل', 'هاتف ذكي'],
        'هاتف ذكي'   => ['ايفون', 'موبايل'],

        // ─── 🌍 Arabic - Tech ─────────────────────────────────────────
        'لابتوب'     => ['حاسوب', 'كمبيوتر'],
        'حاسوب'      => ['لابتوب', 'كمبيوتر'],
        'كمبيوتر'    => ['حاسوب', 'لابتوب'],
        'تابلت'      => ['آيباد', 'لوحي'],
        'آيباد'      => ['تابلت', 'لوحي'],
        'شاشة'       => ['تلفزيون', 'ديسبلاي'],
        'تلفزيون'    => ['شاشة', 'تلفاز'],
        'تلفاز'      => ['تلفزيون', 'شاشة'],
        'سماعة'      => ['هيدفون', 'ايربود'],
        'كاميرا'     => ['تصوير', 'عدسة'],

        // ─── 🌍 Arabic - E-commerce ──────────────────────────────────
        'عرض'        => ['خصم', 'تخفيض'],
        'خصم'        => ['عرض', 'تخفيض'],
        'تخفيض'      => ['خصم', 'عرض'],
        'سعر'        => ['تكلفة', 'ثمن'],
        'ثمن'        => ['سعر', 'تكلفة'],
        'شراء'       => ['طلب', 'اقتناء'],
        'متجر'       => ['محل', 'سوق'],
        'محل'        => ['متجر', 'سوق'],

        // ─── 🌍 Arabic - Content ──────────────────────────────────────
        'مقال'       => ['منشور', 'محتوى'],
        'منشور'      => ['مقال', 'محتوى'],
        'دليل'       => ['شرح', 'توضيح'],
        'شرح'        => ['دليل', 'توضيح'],
        'مراجعة'     => ['تقييم', 'رأي'],
        'تقييم'      => ['مراجعة', 'تقرير'],
        'أخبار'      => ['مستجدات', 'تحديثات'],
        'تحديث'      => ['أخبار', 'جديد'],
    ];

    // ─────────────────────────────────────────────────────────────────

    /**
     * إرجاع المرادفات لكلمة واحدة
     *
     * @return string[]
     */
    public function getSynonyms(string $word): array
    {
        $word     = mb_strtolower(trim($word), 'UTF-8');
        $synonyms = self::SYNONYM_MAP[$word] ?? [];

        // اقطع عند الحد الأقصى
        return array_slice($synonyms, 0, self::MAX_SYNONYMS_PER_WORD);
    }

    /**
     * توسيع مصفوفة كلمات - كل كلمة تصبح مجموعة [كلمة + مرادفاتها]
     *
     * مثال:
     *   Input:  ["جوال", "سامسونج"]
     *   Output: [
     *     ["جوال", "هاتف", "موبايل"],  ← جوال + مرادفاته
     *     ["سامسونج"],                  ← لا مرادفات
     *   ]
     *
     * @param  string[]   $words
     * @return string[][] مصفوفة من المجموعات
     */
    public function expandWords(array $words): array
    {
        $groups = [];

        foreach ($words as $word) {
            $synonyms = $this->getSynonyms($word);

            // الكلمة الأصلية + مرادفاتها في نفس المجموعة
            $groups[] = array_merge([$word], $synonyms);
        }

        return $groups;
    }

    /**
     * هل توجد مرادفات لهذه الكلمة؟
     */
    public function hasSynonyms(string $word): bool
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        return isset(self::SYNONYM_MAP[$word]);
    }
}