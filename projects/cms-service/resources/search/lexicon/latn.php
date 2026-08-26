<?php

declare(strict_types=1);

/**
 * الموارد اللغوية للـ script اللاتيني.
 *
 * القوائم هنا إنجليزية في معظمها لأنها لغة المحتوى الغالبة، لكن الملف
 * يخدم كل ما يُكتب بالحرف اللاتيني. إضافة الفرنسية أو الإسبانية تعني
 * إضافة مفاتيحها إلى القوائم نفسها — لا ملفاً جديداً، لأن الـ script واحد.
 *
 * كل المفاتيح بعد التطبيع: أحرف صغيرة وبلا علامات (café → cafe).
 */
return [

    'stopwords' => [
        'a', 'an', 'the', 'and', 'or', 'but', 'nor', 'yet', 'so',
        'in', 'on', 'at', 'to', 'of', 'for', 'with', 'by', 'from',
        'up', 'about', 'into', 'through', 'during', 'before', 'after',
        'above', 'below', 'between', 'out', 'off', 'over', 'under',
        'again', 'then', 'once', 'here', 'there', 'all', 'both', 'each',
        'more', 'most', 'other', 'some', 'such', 'than', 'too', 'very',
        'just', 'because', 'as', 'until', 'while', 'although', 'if',
        'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it',
        'its', 'they', 'them', 'their', 'this', 'that', 'these', 'those',
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'shall', 'can',
        // الفرنسية والإسبانية والألمانية: نفس الـ script
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'et', 'ou', 'dans',
        'el', 'los', 'las', 'una', 'y', 'o', 'en', 'con', 'por', 'para',
        'der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'mit', 'von',
    ],

    /*
     | كلمات الحشو: تصف رغبة الباحث لا المستند المطلوب.
     |
     | لاحظ غياب "best" و"top" و"cheap" عن هذه القائمة رغم وجودها في
     | النسخة السابقة: هذه كلمات تحمل معنى فعلياً وترد في عناوين حقيقية
     | ("Best Practices"، "Cheap Flights")، وحذفها كان يُفقد نتائج صحيحة.
     | مكانها الصحيح إشارات النية أدناه، حيث تُرجّح ولا تُحذف.
     */
    'fillers' => [
        'want', 'need', 'looking', 'find', 'show', 'give', 'tell', 'help',
        'please', 'gimme', 'lemme', 'wanna', 'gotta',
        'searching', 'search', 'seeking', 'seek',
    ],

    'negation_cues' => [
        'i dont want' => 3,
        'i do not want' => 3,
        'not looking for' => 3,
        'without' => 1,
        'excluding' => 1,
        'except' => 1,
        'other than' => 1,
        'no' => 1,
        'not' => 1,
        'sans' => 1,
        'sin' => 1,
        'ohne' => 1,
    ],

    'temporal_cues' => [
        'released', 'release', 'launched', 'launch', 'came out', 'debuted',
        'introduced', 'announced', 'published', 'made', 'built',
        'year', 'model', 'edition', 'generation', 'version', 'from', 'since',
        'anno', 'annee', 'jahr', 'ano',
    ],

    'relative_time' => [
        'this year' => 0,
        'current year' => 0,
        'last year' => -1,
        'previous year' => -1,
        'two years ago' => -2,
        'next year' => 1,
    ],

    'range_cues' => [
        'under' => 'lte',
        'below' => 'lte',
        'less than' => 'lte',
        'cheaper than' => 'lte',
        'up to' => 'lte',
        'at most' => 'lte',
        'max' => 'lte',
        'over' => 'gte',
        'above' => 'gte',
        'more than' => 'gte',
        'at least' => 'gte',
        'starting at' => 'gte',
        'min' => 'gte',
        'between' => 'range',
        'from' => 'range_start',
        'to' => 'range_end',
    ],

    'attributes' => [
        'black' => ['color' => 'black'],
        'white' => ['color' => 'white'],
        'red' => ['color' => 'red'],
        'blue' => ['color' => 'blue'],
        'green' => ['color' => 'green'],
        'gold' => ['color' => 'gold'],
        'silver' => ['color' => 'silver'],
        'gray' => ['color' => 'gray'],
        'grey' => ['color' => 'gray'],
        'pink' => ['color' => 'pink'],
        'purple' => ['color' => 'purple'],
        'new' => ['condition' => 'new'],
        'used' => ['condition' => 'used'],
        'refurbished' => ['condition' => 'refurbished'],
        'renewed' => ['condition' => 'refurbished'],
    ],

    'intent_signals' => [
        'buy' => ['buy', 2.0],
        'purchase' => ['buy', 2.0],
        'order' => ['buy', 2.0],
        'price' => ['buy', 2.0],
        'pricing' => ['buy', 2.0],
        'cost' => ['buy', 1.5],
        'cheap' => ['buy', 1.5],
        'affordable' => ['buy', 1.5],
        'discount' => ['buy', 1.5],
        'deal' => ['buy', 1.5],
        'sale' => ['buy', 1.5],
        'shipping' => ['buy', 2.0],
        'delivery' => ['buy', 2.0],
        'shop' => ['buy', 1.5],
        'store' => ['buy', 1.5],
        'repair' => ['repair', 2.0],
        'fix' => ['repair', 2.0],
        'service' => ['repair', 2.0],
        'maintenance' => ['repair', 2.0],
        'broken' => ['repair', 1.5],
        'booking' => ['repair', 2.0],
        'appointment' => ['repair', 2.0],
        'install' => ['repair', 1.5],
        'setup' => ['repair', 1.5],
        'vs' => ['compare', 2.0],
        'versus' => ['compare', 2.0],
        'compare' => ['compare', 2.0],
        'comparison' => ['compare', 2.0],
        'difference' => ['compare', 2.0],
        'better' => ['compare', 1.5],
        'best' => ['compare', 1.0],
        'top' => ['compare', 1.0],
        'alternative' => ['compare', 1.5],
        'how' => ['learn', 2.0],
        'why' => ['learn', 1.5],
        'what' => ['learn', 1.0],
        'tutorial' => ['learn', 2.0],
        'guide' => ['learn', 2.0],
        'learn' => ['learn', 2.0],
        'explain' => ['learn', 1.5],
        'definition' => ['learn', 1.5],
        'review' => ['learn', 1.5],
        'documentation' => ['learn', 1.5],
        'news' => ['learn', 1.0],
    ],
];
