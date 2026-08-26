<?php

declare(strict_types=1);

/**
 * موارد لغوية محايدة عن الـ script.
 *
 * كل ما هنا يعمل بغضّ النظر عن لغة الاستعلام، لأنه يعتمد على الأرقام
 * والرموز التي وحّدها TextFolder إلى صورة ASCII واحدة.
 *
 * ─── كيف تُضاف لغة جديدة ─────────────────────────────────────────────
 *
 * أنشئ ملفاً باسم رمز الـ script بأحرف صغيرة في هذا المجلد:
 *
 *     resources/search/lexicon/cyrl.php   (روسي، أوكراني، صربي...)
 *     resources/search/lexicon/hani.php   (صيني)
 *     resources/search/lexicon/deva.php   (هندي، مراثي، نيبالي...)
 *
 * ولا شيء غير ذلك — لا تسجيل ولا تعديل كود. Lexicon يكتشف الملف تلقائياً.
 *
 * ─── ما الذي يعمل بدون ملف أصلاً ─────────────────────────────────────
 *
 * لغة بلا ملف lexicon تبقى مدعومة بالكامل في: تطبيع Unicode، التقسيم
 * الواعي بالـ script، BM25F، استخراج السنوات والأرقام والنطاقات،
 * والفلاتر البنيوية. الملف يضيف فقط: كلمات الوقف، كلمات الحشو، دوالّ
 * النفي والزمن والنطاق، والترجمة الصوتية.
 *
 * أي أن الملف تحسينٌ للجودة، لا شرطٌ للتشغيل.
 */
return [

    /*
     | دوالّ النطاق: كلمة → المُعامِل الذي تفرضه على الرقم التالي لها.
     |
     | المفتاح يُطابَق بعد التطبيع، فتكفي صورة واحدة لكل تعبير.
     */
    'range_cues' => [
        '<' => 'lte',
        '<=' => 'lte',
        '>' => 'gte',
        '>=' => 'gte',
        '-' => 'range',
    ],

    /*
     | وحدات القياس المعروفة: تُلتقط مع الرقم فتصير سمة بنيوية مفهرسة
     | بدل أن تضيع ككلمة عادية.
     |
     | "128gb" تصبح storage=128 لا رمزاً نصياً، فيصح ترتيبها ومقارنتها
     | ويطابق الاستعلام "أكثر من 64 جيجا" ما لم يذكر الرقم 128 حرفياً.
     */
    'units' => [
        'gb' => ['key' => 'storage', 'factor' => 1],
        'tb' => ['key' => 'storage', 'factor' => 1024],
        'mb' => ['key' => 'storage', 'factor' => 0.001],
        'mp' => ['key' => 'camera', 'factor' => 1],
        'mah' => ['key' => 'battery', 'factor' => 1],
        'hz' => ['key' => 'refresh_rate', 'factor' => 1],
        'ghz' => ['key' => 'clock_speed', 'factor' => 1],
        'kg' => ['key' => 'weight', 'factor' => 1000],
        'g' => ['key' => 'weight', 'factor' => 1],
        'cm' => ['key' => 'length', 'factor' => 10],
        'mm' => ['key' => 'length', 'factor' => 1],
        'inch' => ['key' => 'screen_size', 'factor' => 1],
        '"' => ['key' => 'screen_size', 'factor' => 1],
    ],

    /*
     | رموز العملات: وجودها يجعل الرقم المجاور سعراً بثقة عالية.
     */
    'currency_symbols' => ['$', '€', '£', '¥', '₹', '﷼', 'usd', 'eur', 'gbp', 'sar', 'aed', 'egp'],

    /*
     | توحيد أسماء الحقول: اسم الحقل كما أدخله صاحب المحتوى → المفتاح
     | القانوني الذي يستخرجه محلّل الاستعلام.
     |
     | هذا الجسر ضروري لأن طرفَي المعادلة مستقلان: المستخدم يبحث بـ
     | "نزل بال 2020" فيُنتج المحلّل مفتاح "year"، بينما صاحب المحتوى
     | سمّى حقله "Release Year" أو "model_year" أو "سنة الإصدار". بلا
     | توحيد يبحث الطرفان في مفتاحين مختلفين فلا يلتقيان أبداً.
     |
     | ما لا يرد هنا يُفهرس باسمه المطبَّع كما هو — فلا يضيع حقل، لكن
     | لن تجد أسئلة اللغة الطبيعية طريقها إليه.
     */
    'attribute_aliases' => [
        'year' => 'year',
        'years' => 'year',
        'release_year' => 'year',
        'released' => 'year',
        'release_date' => 'year',
        'launch_year' => 'year',
        'model_year' => 'year',
        'production_year' => 'year',
        'edition_year' => 'year',
        'سنه' => 'year',
        'سنه_الاصدار' => 'year',
        'عام_الاصدار' => 'year',
        'تاريخ_الاصدار' => 'year',
        'موديل' => 'year',

        'price' => 'price',
        'cost' => 'price',
        'amount' => 'price',
        'sale_price' => 'price',
        'base_price' => 'price',
        'unit_price' => 'price',
        'سعر' => 'price',
        'السعر' => 'price',
        'ثمن' => 'price',
        'التكلفه' => 'price',

        'color' => 'color',
        'colour' => 'color',
        'colors' => 'color',
        'لون' => 'color',
        'اللون' => 'color',

        'storage' => 'storage',
        'capacity' => 'storage',
        'memory' => 'storage',
        'disk' => 'storage',
        'سعه' => 'storage',
        'التخزين' => 'storage',

        'brand' => 'brand',
        'manufacturer' => 'brand',
        'maker' => 'brand',
        'vendor' => 'brand',
        'ماركه' => 'brand',
        'العلامه_التجاريه' => 'brand',
        'الشركه' => 'brand',

        'condition' => 'condition',
        'state' => 'condition',
        'الحاله' => 'condition',

        'screen_size' => 'screen_size',
        'display_size' => 'screen_size',
        'size' => 'size',
        'weight' => 'weight',
        'battery' => 'battery',
        'camera' => 'camera',
        'model' => 'model',
        'sku' => 'sku',
        'category' => 'category',
        'rating' => 'rating',
        'stock' => 'stock',
    ],
];
