<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | الاسترجاع (Retrieval)
    |--------------------------------------------------------------------------
    |
    | نافذة المرشّحين هي عدد الصفوف التي تُسحب من MySQL قبل إعادة الترتيب
    | في PHP. يجب أن تغطّي الصفحة المطلوبة بالكامل، وإلا رجعت الصفحات
    | البعيدة فارغة — وهي العلّة التي كانت في النسخة السابقة (OFFSET 0 ثابت).
    |
    | candidate_multiplier: كم ضعفاً من (page × per_page) نسحب، ليجد
    | المُرتِّب مجالاً يرفع فيه نتيجة كانت متأخرة في ترتيب MySQL الخام.
    |
    */
    'retrieval' => [
        'candidate_multiplier' => (int) env('SEARCH_CANDIDATE_MULTIPLIER', 4),
        'min_candidates' => (int) env('SEARCH_MIN_CANDIDATES', 200),
        'max_candidates' => (int) env('SEARCH_MAX_CANDIDATES', 1000),
        'count_cap' => (int) env('SEARCH_COUNT_CAP', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | الترتيب (Ranking)
    |--------------------------------------------------------------------------
    |
    | BM25F هو المعيار الفعلي في استرجاع المعلومات. المعاملات:
    |
    |   k1 — تشبّع تكرار الكلمة. كلما ارتفع، زاد أثر التكرار. القيم
    |        المعتادة 1.2–2.0؛ 1.2 مناسب لمحتوى CMS القصير نسبياً.
    |
    |   b  — تطبيع الطول. 0 = تجاهل طول المستند، 1 = تطبيع كامل.
    |        0.75 هو الافتراضي القياسي.
    |
    | أوزان الحقول تعبّر عن أن مطابقة في العنوان أدلّ بكثير من مطابقة في
    | المتن — وهي المعلومة التي كان MySQL يهدرها لأن MATCH() يعامل كل
    | الأعمدة على أنها حقل واحد مسطّح.
    |
    */
    'ranking' => [
        'bm25' => [
            'k1' => (float) env('SEARCH_BM25_K1', 1.2),
            'b' => (float) env('SEARCH_BM25_B', 0.75),
        ],

        'field_weights' => [
            'title' => (float) env('SEARCH_WEIGHT_TITLE', 5.0),
            'content' => (float) env('SEARCH_WEIGHT_CONTENT', 1.0),
            'meta' => (float) env('SEARCH_WEIGHT_META', 2.0),
        ],

        /*
         | أوزان الإشارات السلوكية، مطبَّقة على قيم مطبَّعة في [0,1].
         | مجموعها يجب أن يبقى أصغر بوضوح من مدى BM25 حتى تظل الصلة
         | هي المحرّك الأساسي ولا تختطف الشعبيةُ الترتيبَ.
         */
        'signals' => [
            'ctr' => (float) env('SEARCH_SIGNAL_CTR', 1.5),
            'popularity' => (float) env('SEARCH_SIGNAL_POPULARITY', 1.0),
            'freshness' => (float) env('SEARCH_SIGNAL_FRESHNESS', 0.8),
            'exact_phrase' => (float) env('SEARCH_SIGNAL_PHRASE', 3.0),
            'attribute_match' => (float) env('SEARCH_SIGNAL_ATTRIBUTE', 4.0),
            'intent_match' => (float) env('SEARCH_SIGNAL_INTENT', 1.2),
        ],

        /*
         | التخصيص يُطبَّق كسقف نسبي لا كإضافة مفتوحة.
         |
         | السبب: الإضافة المفتوحة تخلق فقاعة — من نقر على الهواتف مرّة
         | يرى الهواتف في كل بحث لاحق مهما كان استعلامه. السقف يضمن أن
         | التخصيص يرجّح بين نتائج متقاربة الصلة ولا يقلب ترتيباً واضحاً.
         |
         | max_boost = 0.25 يعني: التخصيص لا يرفع نتيجة أكثر من 25%.
         */
        'personalization' => [
            'enabled' => (bool) env('SEARCH_PERSONALIZATION_ENABLED', true),
            'max_boost' => (float) env('SEARCH_PERSONALIZATION_MAX_BOOST', 0.25),
            'half_life_days' => (float) env('SEARCH_PERSONALIZATION_HALF_LIFE', 7.0),
            'history_days' => (int) env('SEARCH_PERSONALIZATION_HISTORY_DAYS', 30),
            'cache_ttl_minutes' => (int) env('SEARCH_PERSONALIZATION_CACHE_TTL', 15),
        ],

        /*
         | نصف عمر الحداثة بالأيام: بعده تنخفض إشارة الحداثة إلى النصف.
         |
         | الصيغة السابقة 1/(days+1) كانت تنهار بسرعة مفرطة — مقال عمره
         | أسبوع كان يحتفظ بـ 12% فقط من قيمة مقال اليوم، فتحوّل البحث
         | فعلياً إلى ترتيب زمني. الانحلال الأسّي أكثر اعتدالاً وقابل
         | للضبط بمعنى واضح.
         */
        'freshness_half_life_days' => (float) env('SEARCH_FRESHNESS_HALF_LIFE', 45.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | فهم الاستعلام (Query Understanding)
    |--------------------------------------------------------------------------
    |
    | الإشارة الغامضة تصير ترجيحاً، والإشارة القاطعة تصير فلتراً.
    |
    | "ايفون 2020" — الرقم قد يكون سنة إصدار وقد يكون جزءاً من اسم
    | الموديل، فلا يصحّ إقصاء نتائج بناءً عليه: يصير ترجيحاً.
    |
    | "ايفون نزل بال 2020" — وجود دالّ زمني صريح يرفع الثقة فوق العتبة،
    | فيصير فلتراً حقيقياً يُقصي فعلاً.
    |
    */
    'understanding' => [
        'filter_confidence_threshold' => (float) env('SEARCH_FILTER_CONFIDENCE', 0.80),
        'max_terms' => (int) env('SEARCH_MAX_TERMS', 12),
        'max_filters' => (int) env('SEARCH_MAX_FILTERS', 6),
        'min_year' => 1900,
        'max_year' => 2100,
    ],

    /*
    |--------------------------------------------------------------------------
    | الذكاء الاصطناعي — احتياطي محكوم فقط
    |--------------------------------------------------------------------------
    |
    | لا يُستدعى إلا بعد فشل المسار المحلّي في إيجاد أي نتيجة. مخرجاته
    | تُقولب في QueryPlan نفسه ولا تُحقن نصّاً خاماً في أي استعلام.
    |
    | كل استعلام مميّز يكلّف استدعاءً واحداً على الأكثر مدى الحياة: النتيجة
    | تُخزَّن في search_query_plans مفتاحُها بصمة الاستعلام.
    |
    */
    'ai' => [
        'enabled' => (bool) env('AI_SEARCH_ENABLED', false),
        'timeout_seconds' => (float) env('AI_SEARCH_TIMEOUT', 3.0),
        'plan_cache_days' => (int) env('AI_SEARCH_PLAN_CACHE_DAYS', 30),

        /*
         | قاطع الدارة: بعد هذا العدد من الإخفاقات المتتالية يتوقف النظام
         | عن محاولة الاتصال لمدة cooldown. بدونه يتحوّل عطل في المزوّد
         | إلى إضافة ثوانٍ على كل بحث فاشل في الموقع كله.
         */
        'circuit_breaker' => [
            'failure_threshold' => (int) env('AI_SEARCH_FAILURE_THRESHOLD', 3),
            'cooldown_seconds' => (int) env('AI_SEARCH_COOLDOWN', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | الكاش
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => (bool) env('SEARCH_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('SEARCH_CACHE_TTL', 600),
        'hot_ttl_seconds' => (int) env('SEARCH_CACHE_HOT_TTL', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | الفهرسة
    |--------------------------------------------------------------------------
    |
    | ngram_token_size يجب أن يساوي innodb_ngram_token_size في خادم MySQL.
    | اختلافهما يعني أننا نبحث عن وحدات غير التي فُهرست، فلا تطابق أبداً.
    |
    */
    'indexing' => [
        'ngram_token_size' => (int) env('SEARCH_NGRAM_TOKEN_SIZE', 2),
        'chunk_size' => (int) env('SEARCH_INDEX_CHUNK_SIZE', 100),
        'max_content_length' => (int) env('SEARCH_MAX_CONTENT_LENGTH', 65535),
    ],
];
