# HyperCore Search Engine - توثيق كامل

> نظام بحث متكامل مبني داخل منصة HyperCore باستخدام Laravel و MySQL FULLTEXT

---

## فهرس المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [البنية المعمارية](#البنية-المعمارية)
3. [هيكل الملفات](#هيكل-الملفات)
4. [قاعدة البيانات](#قاعدة-البيانات)
5. [الخطوة 1 - نظام الفهرسة](#الخطوة-1---نظام-الفهرسة)
6. [الخطوة 2 - ربط الفهرسة بدورة حياة CMS](#الخطوة-2---ربط-الفهرسة-بدورة-حياة-cms)
7. [الخطوة 3 - البحث الفعلي](#الخطوة-3---البحث-الفعلي)
8. [الخطوة 4 - إصلاح FULLTEXT](#الخطوة-4---إصلاح-fulltext)
9. [الخطوة 5 - معالجة الكلمات](#الخطوة-5---معالجة-الكلمات)
10. [الخطوة 6 - Query Relaxation](#الخطوة-6---query-relaxation)
11. [الخطوة 7 - Snippet و Highlighting](#الخطوة-7---snippet-و-highlighting)
12. [الخطوة 8 - Advanced Ranking](#الخطوة-8---advanced-ranking)
13. [الخطوة 9 - أمر إعادة الفهرسة](#الخطوة-9---أمر-إعادة-الفهرسة)
14. [الخطوة 10 - Progressive Relaxation](#الخطوة-10---progressive-relaxation)
15. [الخطوة 11 - Synonym Expansion](#الخطوة-11---synonym-expansion)
16. [الخطوة 12 - Intent Detection](#الخطوة-12---intent-detection)
17. [تدفق البيانات الكامل](#تدفق-البيانات-الكامل)
18. [كيفية الاختبار](#كيفية-الاختبار)
19. [الـ API Reference](#الـ-api-reference)

---

## نظرة عامة

نظام بحث متكامل مشابه لـ Google (مُبسَّط) مدمج مع نظام CMS الديناميكي في HyperCore.

### الميزات الرئيسية

| الميزة | الوصف |
|---|---|
| **MySQL FULLTEXT** | بحث نصي سريع بدون Elasticsearch |
| **Automatic Indexing** | الفهرسة تلقائياً عند إنشاء/تعديل DataEntry |
| **Keyword Processing** | تنظيف الكلمات وحذف stop words |
| **Progressive Relaxation** | 4 مراحل من الصارم للمرن |
| **Synonym Expansion** | توسيع البحث بالمرادفات |
| **Intent Detection** | كشف نية المستخدم (product/article/service) |
| **Smart Ranking** | 5 عوامل تؤثر في ترتيب النتائج |
| **Highlighting** | تمييز الكلمات في النتائج |
| **Smart Snippets** | مقتطفات نصية متمركزة حول الكلمة |
| **Bulk Reindex** | إعادة بناء الفهرس كاملاً بأمر واحد |

---

## البنية المعمارية

```
API Request
    │
    ▼
SearchController          ← HTTP layer فقط
    │
    ▼
SearchRequest             ← Validation
    │
    ▼
SearchService             ← Orchestration
    │
    ▼
SearchEntriesAction       ← Business Logic
    │
    ├── KeywordProcessor      → تنظيف + stop words + توسيع مرادفات + كشف نية
    │       ├── SynonymProvider   → قاموس المرادفات
    │       └── IntentDetector    → كشف نية المستخدم
    │
    └── SearchRepository      → SQL + Ranking
            │
            ▼
        search_index table    ← MySQL FULLTEXT
```

### القاعدة الثابتة

```
API → Controller → Request → Service → Action → Repository
```

- **Controllers**: HTTP فقط، لا منطق
- **Requests**: Validation فقط
- **Services**: تنسيق بين Actions
- **Actions**: منطق عمل محدد الهدف
- **Repositories**: قواعد بيانات فقط، لا منطق
- **DTOs**: نقل البيانات بين الطبقات

---

## هيكل الملفات

```
app/
├── Console/
│   └── Commands/
│       └── SearchReindexCommand.php
│
├── Domains/
│   └── Search/
│       ├── Actions/
│       │   ├── IndexDataEntryAction.php
│       │   ├── SearchEntriesAction.php
│       │   └── ReindexSearchAction.php
│       │
│       ├── DTOs/
│       │   ├── IndexEntryDTO.php
│       │   ├── SearchQueryDTO.php
│       │   ├── SearchResultDTO.php
│       │   └── SearchResultItemDTO.php
│       │
│       ├── Events/
│       │   └── DataEntrySavedEvent.php
│       │
│       ├── Http/
│       │   ├── Controllers/
│       │   │   └── SearchController.php
│       │   └── Requests/
│       │       └── SearchRequest.php
│       │
│       ├── Listeners/
│       │   └── IndexDataEntryListener.php
│       │
│       ├── Models/
│       │   └── SearchIndex.php
│       │
│       ├── Repositories/
│       │   ├── Interfaces/
│       │   │   ├── SearchIndexRepositoryInterface.php
│       │   │   └── SearchRepositoryInterface.php
│       │   └── Eloquent/
│       │       ├── EloquentSearchIndexRepository.php
│       │       └── EloquentSearchRepository.php
│       │
│       ├── Services/
│       │   └── SearchService.php
│       │
│       └── Support/
│           ├── EntryFieldsExtractor.php
│           ├── IntentDetector.php
│           ├── KeywordProcessor.php
│           ├── ProcessedKeyword.php
│           └── SynonymProvider.php
│
├── Providers/
│   ├── EventServiceProvider.php
│   └── SearchServiceProvider.php
│
database/
├── migrations/
│   └── 2026_04_22_000001_create_search_index_table.php
└── seeders/
    └── SearchIndexSeeder.php
```

---

## قاعدة البيانات

### جدول `search_index`

```sql
CREATE TABLE search_index (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- المصدر
    entry_id      BIGINT UNSIGNED NOT NULL,
    data_type_id  BIGINT UNSIGNED NOT NULL,
    project_id    BIGINT UNSIGNED NOT NULL,

    -- بيانات الفهرسة
    language      VARCHAR(10)  DEFAULT 'en',
    title         VARCHAR(255) NULL,       -- أعلى وزن في الـ ranking
    content       LONGTEXT     NULL,       -- وزن متوسط
    meta          LONGTEXT     NULL,       -- JSON - أدنى وزن

    -- بيانات مساعدة
    status        VARCHAR(20)  DEFAULT 'published',
    published_at  TIMESTAMP    NULL,

    created_at    TIMESTAMP    NULL,
    updated_at    TIMESTAMP    NULL,

    -- Constraints
    UNIQUE KEY search_index_entry_lang_unique (entry_id, language),

    -- Indexes
    INDEX search_project_type_lang_idx (project_id, data_type_id, language),
    INDEX search_project_status_lang_idx (project_id, status, language),

    -- FULLTEXT Index (الأساس)
    FULLTEXT KEY fulltext_title_content (title, content)
);
```

### القاعدة المهمة - FULLTEXT Index

```
MATCH() يجب أن يستخدم نفس أعمدة الـ FULLTEXT index تماماً

✅ صحيح:  MATCH(title, content) AGAINST(?)
❌ خاطئ:  MATCH(title) AGAINST(?)
          MATCH(content) AGAINST(?)
```

### العلاقة مع جداول CMS

```
data_entries (id)    ──→  search_index (entry_id)
data_types (id)      ──→  search_index (data_type_id)
projects (id)        ──→  search_index (project_id)

سجل واحد في search_index = entry واحد × لغة واحدة
entry_id=1, language='en'  →  سجل واحد
entry_id=1, language='ar'  →  سجل ثانٍ
```

---

## الخطوة 1 - نظام الفهرسة

### الهدف
تحويل DataEntries إلى سجلات قابلة للبحث في `search_index`.

### المكونات

#### `IndexEntryDTO`
```php
new IndexEntryDTO(
    entryId:     1,
    dataTypeId:  1,
    projectId:   1,
    language:    'en',
    title:       'iPhone 15 Pro',
    content:     'Best smartphone with...',
    meta:        ['tags' => 'iphone, apple'],
    status:      'published',
    publishedAt: '2026-01-01 00:00:00',
);
```

#### `EntryFieldsExtractor`
يحلل `entry->values` ويصنف الحقول:

| نوع الحقل | يذهب إلى |
|---|---|
| `text`, `string`, `title` | `title` |
| `textarea`, `richtext`, `wysiwyg` | `content` |
| باقي الأنواع | `meta` |

#### `IndexDataEntryAction`
```
entry
  → loadMissing(['values', 'values.field', 'project'])
  → resolveSupportedLanguages()   ← من project->supported_languages
  → لكل لغة: extractor->extract() + repository->upsert()
```

#### `EloquentSearchIndexRepository`
```php
// updateOrCreate بناءً على (entry_id + language)
SearchIndex::updateOrCreate(
    ['entry_id' => $dto->entryId, 'language' => $dto->language],
    [/* باقي البيانات */]
);
```

---

## الخطوة 2 - ربط الفهرسة بدورة حياة CMS

### الهدف
الفهرسة تعمل تلقائياً بدون استدعاء يدوي.

### تدفق الأحداث

```
DataEntryService::create() / update()
    │
    └── event(new DataEntrySavedEvent($entry))
                │
                ▼ Queue: search-indexing
    IndexDataEntryListener::handle()
                │
                ├── تحقق: status = 'published'?
                │     لا  → تجاهل (draft/scheduled)
                │     نعم → تابع
                │
                └── IndexDataEntryAction::execute($entry)
```

### إعدادات الـ Listener

```php
class IndexDataEntryListener implements ShouldQueue
{
    public string $queue = 'search-indexing';  // queue منفصل
    public int    $tries = 3;                   // 3 محاولات
    public int    $backoff = 10;                // 10 ثانية بين المحاولات
}
```

### تشغيل الـ Queue

```bash
# تشغيل queue worker
php artisan queue:work --queue=search-indexing

# للتطوير: تنفيذ فوري بدون queue
# في .env
QUEUE_CONNECTION=sync
```

---

## الخطوة 3 - البحث الفعلي

### الـ API Endpoint

```
GET /api/search
Headers: X-Project-Id, Authorization
Query Params: q, lang, data_type_slug, page, per_page
```

### مثال Request/Response

```http
GET /api/search?q=laravel&lang=en&page=1&per_page=10
X-Project-Id: 1
Authorization: Bearer token
```

```json
{
  "keyword": "laravel",
  "meta": {
    "total": 5,
    "page": 1,
    "per_page": 10,
    "last_page": 1
  },
  "results": [
    {
      "entry_id": 1,
      "data_type_id": 2,
      "project_id": 1,
      "language": "en",
      "title": "**Laravel** Framework Tutorial",
      "snippet": "...Learn **Laravel** from scratch with this guide...",
      "status": "published",
      "score": 7.25,
      "published_at": "2026-01-01 00:00:00"
    }
  ]
}
```

### DTOs البحث

```
SearchQueryDTO   → مدخلات البحث
      ↓
SearchEntriesAction
      ↓
SearchResultDTO  → النتيجة الكاملة
  └── SearchResultItemDTO[]  → كل نتيجة
```

---

## الخطوة 4 - إصلاح FULLTEXT

### المشاكل التي تم إصلاحها

| المشكلة | السبب | الحل |
|---|---|---|
| Error 1191 | `MATCH(title)` لا يجد index | استخدام `MATCH(title, content)` دائماً |
| اسم الجدول | `search_indices` خاطئ | `search_index` الصحيح |
| Score = 0 | BOOLEAN MODE لا يُنتج scores | NATURAL LANGUAGE في SELECT، BOOLEAN في WHERE |
| Ranking مكسور | نفس مشكلة المشكلة 1 | `LOCATE()` كـ title bonus بديلاً |

### الاستراتيجية النهائية

```sql
-- WHERE: BOOLEAN MODE للفلترة الدقيقة
WHERE MATCH(title, content) AGAINST('+laravel*' IN BOOLEAN MODE)

-- SELECT: NATURAL LANGUAGE للـ scoring الحقيقي
MATCH(title, content) AGAINST('+laravel*' IN NATURAL LANGUAGE MODE)
* (1 + (2 * (LOCATE('laravel', title) > 0)))
```

---

## الخطوة 5 - معالجة الكلمات

### `KeywordProcessor::process()`

```
"i need laravel framework tutorial"
         │
         ▼
1. cleanInput()        → "i need laravel framework tutorial"
2. tokenize()          → ["i","need","laravel","framework","tutorial"]
3. removeStopWords()   → ["laravel","framework","tutorial"]
                           (حُذف: i, need)
4. buildBooleanQuery() → "+laravel* +framework* +tutorial*"
```

### Stop Words

قائمة تشمل كلمات شائعة في:
- **الإنجليزية**: i, me, the, a, an, is, are, have, do, will, ...
- **العربية**: في، من، إلى، على، عن، هذا، هو، هي، ...

### الحد الأدنى لطول الكلمة
```php
private const MIN_WORD_LENGTH = 2;  // كلمات أقل من حرفين تُحذف
private const MAX_WORDS       = 10; // أقصى عدد كلمات للبحث
```

---

## الخطوة 6 - Query Relaxation

### المشكلة
`"+best* +php* +laravel* +tutorial*"` → لا نتائج لأن AND logic صارم جداً.

### الحل - Importance-Based Relaxation

```
أطول كلمة = الأكثر تحديداً = required
باقي الكلمات = optional

"best php laravel tutorial"
→ cleanWords: ["php", "laravel", "tutorial"]  (حُذفت best كـ stop word)
→ أطول كلمة: "tutorial" (8 أحرف)
→ query: "+tutorial* php* laravel*"
```

### متى يتغير السلوك

| عدد الكلمات | السلوك |
|---|---|
| 1 كلمة | `word*` |
| 2 كلمتان | `+word1* +word2*` (كلاهما required) |
| 3+ كلمات | أطول كلمة required، الباقي optional |

---

## الخطوة 7 - Snippet و Highlighting

### Snippet الذكي

```
بدل: أول 160 حرف عشوائي

الجديد:
1. ابحث عن أول كلمة مطابقة في المحتوى
2. خذ 60 حرف قبلها و 100 حرف بعدها
3. أضف "..." للإشارة للاقتطاع

مثال:
"...web development. **Laravel** is a PHP framework used for..."
```

### Highlighting

```
Input:  "Laravel is a great PHP framework"
Words:  ["laravel", "php"]
Output: "**Laravel** is a great **PHP** framework"

الخصائص:
- Case insensitive
- يحافظ على الحالة الأصلية
- يُرتب من الأطول للأقصر لتجنب التداخل
- آمن ضد double highlighting
```

---

## الخطوة 8 - Advanced Ranking

### 5 عوامل تؤثر في الـ Score

```
weighted_score = A + B + C + D_title + D_content + E + F(intent)

A: FULLTEXT base score × 3
   "الصلة العامة بالنص"

B: Title existence boost (+2.0)
   "هل الكلمة موجودة في العنوان؟"

C: Exact LIKE boost (+1.5)
   "هل العنوان يحتوي الكلمة بالضبط؟"
   title LIKE '%keyword%'

D: Position boost (0 → 0.5)
   "كلما بكرت الكلمة → score أعلى"
   1 / (LOCATE(keyword, title) + 1)

E: Frequency boost (×0.1/ظهور)
   "كلما تكررت الكلمة → score أعلى قليلاً"
   CHAR_LENGTH - CHAR_LENGTH(REPLACE) / CHAR_LENGTH(keyword)

F: Intent boost (+0 → +2.5)
   "هل الـ data_type يتطابق مع نية المستخدم؟"
   confidence × 2.5
```

### مثال عملي

```
keyword = "laravel"
Entry A: title = "Laravel Framework Tutorial"  → score: 7.00 ✅
Entry B: title = "PHP Web Development"         → score: 1.94
Entry C: title = "Laravel"                     → score: 6.95 ✅
```

---

## الخطوة 9 - أمر إعادة الفهرسة

### الاستخدام

```bash
# مع تأكيد
php artisan search:reindex

# بدون تأكيد (للـ CI/CD)
php artisan search:reindex --force

# مع تفاصيل الأخطاء
php artisan search:reindex --force -v
```

### Output المتوقع

```
╔══════════════════════════════════════╗
║      Search Index Rebuilder          ║
╚══════════════════════════════════════╝

 950/950 [============================] 100%

✓ Reindex completed successfully.

+---------------------------+-----------------+
| Metric                    | Value           |
+---------------------------+-----------------+
| Total entries processed   | 950             |
| Successfully indexed      | 920             |
| Skipped (no content)      | 30              |
| Time elapsed              | 4.82s           |
| Throughput                | 197 entries/sec |
+---------------------------+-----------------+
```

### كـ Scheduled Job

```php
// كل يوم الساعة 2 صباحاً
Schedule::command('search:reindex --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### الأداء

```
chunk(100) → كل 100 entry = bulk insert واحد
بدلاً من: 1 query × 1000 entry = 1000 queries
الجديد:   1 query × 10 chunks  = 10 queries
```

---

## الخطوة 10 - Progressive Relaxation

### المراحل الأربع

```
Input: ["iphone", "15", "pro"]

Step 0 - STRICT:       "+iphone* +15* +pro*"
                        كل الكلمات مطلوبة (AND)

Step 1 - SEMI-STRICT:  "+iphone* 15* pro*"
                        الأولى مطلوبة، الباقي اختياري

Step 2 - LOOSE:        "iphone* 15* pro*"
                        كل الكلمات اختيارية (OR)

Step 3 - FALLBACK:     "iphone*"
                        الكلمة الأولى فقط
```

### المنطق

```php
foreach ($processed->relaxedQueries as $step => $query) {
    $result = $this->executeSearch($dto, $processed, $query);

    if ($result['total'] > 0) {
        // وجدنا نتائج → توقف هنا
        return $result;
    }
    // لم نجد → جرّب المرحلة التالية
}
```

### حالات خاصة

| عدد الكلمات | عدد المراحل | السبب |
|---|---|---|
| 1 كلمة | 1 مرحلة | لا تحسين ممكن |
| 2 كلمتان | 3 مراحل | STRICT = SEMI-STRICT محذوف |
| 3+ كلمات | 4 مراحل | الحالة الطبيعية |

---

## الخطوة 11 - Synonym Expansion

### المفهوم

```
"جوال سامسونج"
      │
      ▼
expandedGroups:
  [
    ["جوال", "هاتف", "موبايل"],  ← جوال + مرادفاته
    ["سامسونج"],                  ← لا مرادفات
  ]
      │
      ▼
relaxedQueries:
  Step 0: "+(جوال* هاتف* موبايل*) +سامسونج*"
  Step 1: "+(جوال* هاتف* موبايل*) سامسونج*"
  Step 2: "(جوال* هاتف* موبايل*) سامسونج*"
  Step 3: "(جوال* هاتف* موبايل*)"
```

### بناء الـ Group Term

```
كلمة واحدة بدون مرادفات:
  required=true  → "+word*"
  required=false → "word*"

مجموعة بمرادفات:
  required=true  → "+(word* syn1* syn2*)"
  required=false → "(word* syn1* syn2*)"

عبارة متعددة الكلمات:
  "apple phone"  → '"apple phone"'  ← phrase في MySQL
```

### الحد الأقصى للمرادفات
```php
private const MAX_SYNONYMS_PER_WORD = 2;
// لتجنب queries ضخمة تُبطئ MySQL
```

### إضافة مرادفات جديدة

```php
// في SynonymProvider::SYNONYM_MAP
'laptop'     => ['notebook', 'computer'],
'notebook'   => ['laptop', 'computer'],
// يجب أن يكون ثنائي الاتجاه
```

---

## الخطوة 12 - Intent Detection

### أنواع النية

| النية | مثال كلمات | مثال query |
|---|---|---|
| `product` | buy, price, cheap, شراء، سعر | "iphone price" |
| `article` | how, tutorial, guide, شرح، دليل | "laravel tutorial" |
| `service` | repair, booking, appointment, حجز، إصلاح | "phone repair" |
| `general` | لا إشارات واضحة | "laravel framework" |

### حساب الـ Confidence

```
Input: ["phone", "repair", "booking"]

phone   → product: +1.5 (من الـ signals)
repair  → service: +2.0
booking → service: +2.0

rawScores:  { product: 1.5, article: 0, service: 4.0 }
totalScore: 5.5
normalized: { product: 0.27, article: 0, service: 0.73 }

winner:     "service"
confidence: 0.73  > threshold (0.3) ✅
```

### تأثير النية على الـ Ranking

```sql
-- إذا intent = "product" و confidence = 0.8
CASE
    WHEN dt.slug IN ('products', 'items', 'goods')
    THEN 0.8 * 2.5   -- = +2.0 boost
    ELSE 0
END

-- إذا intent = "general"
-- لا boost → النتائج تُرتب بالـ FULLTEXT فقط
```

### الـ INTENT_DATA_TYPE_MAP

```php
// يجب تعديله حسب slugs مشروعك الفعلية
private const INTENT_DATA_TYPE_MAP = [
    'product' => ['products', 'product', 'items', 'goods', 'منتجات'],
    'article' => ['articles', 'article', 'posts', 'blog', 'news', 'مقالات'],
    'service' => ['services', 'service', 'booking', 'appointments', 'خدمات'],
];
```

---

## تدفق البيانات الكامل

```
المستخدم يكتب: "i need iphone repair price"
                          │
                          ▼
              SearchController::__invoke()
                    │ X-Project-Id: 1
                    │ keyword: "i need iphone repair price"
                          │
                          ▼
              SearchRequest::keyword()  →  "i need iphone repair price"
                          │
                          ▼
              SearchService::search(SearchQueryDTO)
                          │
                          ▼
              SearchEntriesAction::execute()
                          │
                          ├─► KeywordProcessor::process()
                          │         │
                          │    cleanInput()    → "i need iphone repair price"
                          │    tokenize()      → ["i","need","iphone","repair","price"]
                          │    stopWords()     → ["iphone","repair","price"]
                          │         │
                          │    IntentDetector::detect(["iphone","repair","price"])
                          │         repair → service: +2.0
                          │         price  → product: +2.0
                          │         → intent: mixed, product wins? service wins?
                          │         → product: 2.0/4.0 = 0.5
                          │         → service: 2.0/4.0 = 0.5
                          │         → DRAW → product يفوز (أعلى alphabetically)
                          │         → confidence: 0.5 > threshold ✅
                          │         │
                          │    SynonymProvider::expandWords()
                          │         iphone → ["iphone","apple phone","ios phone"]
                          │         repair → ["repair","fix","maintenance"]? (إذا أضفته)
                          │         price  → ["price","cost","rate"]? (إذا أضفته)
                          │         │
                          │    buildRelaxedQueries()
                          │         Step 0: '+(iphone* "apple phone" "ios phone") +repair* +price*'
                          │         Step 1: '+(iphone* "apple phone" "ios phone") repair* price*'
                          │         Step 2: '(iphone* "apple phone" "ios phone") repair* price*'
                          │         Step 3: '(iphone* "apple phone" "ios phone")'
                          │         │
                          │    ProcessedKeyword {
                          │        booleanQuery:   Step 0,
                          │        relaxedQueries: [Step 0..3],
                          │        intent:         {intent: 'product', confidence: 0.5},
                          │        cleanWords:     ["iphone","repair","price"],
                          │        primaryWord:    "iphone",
                          │    }
                          │
                          └─► SearchRepository::search()
                                    │
                              foreach relaxedQueries:
                                    │
                              Step 0 → executeSearch()
                                    │   → total = 0 (صارم جداً) ❌
                                    │
                              Step 1 → executeSearch()
                                    │   → total = 3 ✅ توقف!
                                    │
                              SQL:
                              SELECT ...,
                                (FULLTEXT_score × 3)
                                + (title_exists ? 2.0 : 0)
                                + (title_like ? 1.5 : 0)
                                + (1/position_title)
                                + (1/position_content × 0.5)
                                + (frequency × 0.1)
                                + (product_intent ? 0.5×2.5 : 0)
                              AS weighted_score
                              FROM search_index si
                              LEFT JOIN data_types dt ON dt.id = si.data_type_id
                              WHERE si.project_id = 1
                                AND si.language = 'en'
                                AND si.status = 'published'
                                AND MATCH(title,content)
                                    AGAINST('+(iphone* ...) repair* price*' IN BOOLEAN MODE)
                              ORDER BY weighted_score DESC
                                    │
                                    ▼
                              النتائج:
                              1. iPhone Screen Repair Service    score: 9.2  (service)
                              2. iPhone 15 Pro Max - Price       score: 8.1  (product+intent)
                              3. iPhone Repair Cost Guide        score: 6.5  (article)
                                    │
                                    ▼
                          SearchEntriesAction::mapToDTO()
                              title:   "**iPhone** Screen Repair **Service**"
                              snippet: "...Book an appointment for **iPhone** repair..."
                                    │
                                    ▼
                          SearchResultDTO → SearchController → JSON Response
```

---

## كيفية الاختبار

### 1. تشغيل الـ Migration والـ Seeder

```bash
php artisan migrate
php artisan db:seed --class=SearchIndexSeeder
```

### 2. التحقق من البيانات

```sql
SELECT data_type_id, language, COUNT(*) as total
FROM search_index
GROUP BY data_type_id, language;

-- تحقق من FULLTEXT index
SHOW CREATE TABLE search_index;
-- يجب أن ترى: FULLTEXT KEY `fulltext_title_content` (`title`,`content`)
```

### 3. اختبار في Tinker

```bash
php artisan tinker
```

```php
// اختبار كامل
$dto = new \App\Domains\Search\DTOs\SearchQueryDTO(
    keyword:   'iphone repair',
    projectId: 1,
    language:  'en',
    page:      1,
    perPage:   10,
);

$action = app(\App\Domains\Search\Actions\SearchEntriesAction::class);
$result = $action->execute($dto);

foreach ($result->items as $item) {
    echo $item->title . ' [score: ' . $item->score . ']' . PHP_EOL;
    echo $item->snippet . PHP_EOL;
    echo '---' . PHP_EOL;
}
```

### 4. اختبارات موجهة

#### اختبار Intent Detection

```php
$detector = app(\App\Domains\Search\Support\IntentDetector::class);

// يجب أن يكتشف product
$r = $detector->detect(['iphone', 'price', 'cheap']);
assert($r['intent'] === 'product');

// يجب أن يكتشف article
$r = $detector->detect(['laravel', 'tutorial', 'شرح']);
assert($r['intent'] === 'article');

// يجب أن يكتشف service
$r = $detector->detect(['phone', 'repair', 'booking']);
assert($r['intent'] === 'service');

// يجب أن يكون general
$r = $detector->detect(['laravel', 'framework']);
assert($r['intent'] === 'general');
```

#### اختبار Synonym Expansion

```php
$provider = app(\App\Domains\Search\Support\SynonymProvider::class);

// يجب أن يرجع مرادفات
assert($provider->getSynonyms('phone') === ['mobile', 'cellphone']);
assert($provider->getSynonyms('جوال') === ['هاتف', 'موبايل']);
assert($provider->getSynonyms('samsung') === []);
```

#### اختبار Progressive Relaxation

```php
$processor = app(\App\Domains\Search\Support\KeywordProcessor::class);

// query صعب يجب أن يجد نتائج عبر relaxation
$result = $processor->process('best iphone 15 pro max deals');
assert(count($result->relaxedQueries) === 4);
assert(str_contains($result->relaxedQueries[0], '+'));   // STRICT
assert(!str_contains($result->relaxedQueries[2], '+'));  // LOOSE
```

#### اختبار draft لا يظهر

```php
$dto = new \App\Domains\Search\DTOs\SearchQueryDTO(
    keyword: 'DRAFT upcoming',
    projectId: 1, language: 'en', page: 1, perPage: 10
);
$result = app(\App\Domains\Search\Actions\SearchEntriesAction::class)->execute($dto);
assert($result->total === 0);
```

### 5. اختبار عبر API

```bash
# بحث عادي
curl -X GET "http://localhost/api/search?q=iphone&lang=en" \
  -H "X-Project-Id: 1" \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"

# بحث بنية product
curl -X GET "http://localhost/api/search?q=iphone+price+cheap&lang=en" \
  -H "X-Project-Id: 1" \
  -H "Authorization: Bearer TOKEN"

# بحث عربي
curl -X GET "http://localhost/api/search?q=جوال+سامسونج&lang=ar" \
  -H "X-Project-Id: 1" \
  -H "Authorization: Bearer TOKEN"

# بحث مع فلتر data_type
curl -X GET "http://localhost/api/search?q=laravel&lang=en&data_type_slug=articles" \
  -H "X-Project-Id: 1" \
  -H "Authorization: Bearer TOKEN"
```

### 6. إعادة الفهرسة الكاملة

```bash
# بعد إضافة بيانات جديدة
php artisan search:reindex --force
```

---

## الـ API Reference

### `GET /api/search`

#### Headers

| Header | Required | Description |
|---|---|---|
| `X-Project-Id` | ✅ | معرف المشروع |
| `Authorization` | ✅ | Bearer token |
| `Accept` | - | application/json |

#### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `q` | string | **required** | كلمة البحث (min: 2, max: 200) |
| `lang` | string | `en` | اللغة |
| `data_type_slug` | string | null | فلتر نوع البيانات |
| `page` | integer | `1` | رقم الصفحة |
| `per_page` | integer | `15` | عدد النتائج (max: 50) |

#### Response Schema

```json
{
  "keyword": "string",
  "meta": {
    "total": "integer",
    "page": "integer",
    "per_page": "integer",
    "last_page": "integer"
  },
  "results": [
    {
      "entry_id": "integer",
      "data_type_id": "integer",
      "project_id": "integer",
      "language": "string",
      "title": "string (with **highlights**)",
      "snippet": "string (with **highlights**)",
      "status": "string",
      "score": "float",
      "published_at": "datetime"
    }
  ]
}
```

#### Response Codes

| Code | Meaning |
|---|---|
| `200` | نجح البحث (حتى لو النتائج فارغة) |
| `400` | X-Project-Id مفقود |
| `401` | غير مصرح |
| `422` | validation error (q مفقود أو قصير) |

---

## ملاحظات مهمة

### MySQL FULLTEXT

```
1. FULLTEXT لا يعمل مع كلمات أقل من 4 أحرف (افتراضي)
   الحل: أضف في my.cnf:
   ft_min_word_len = 2
   ثم: REPAIR TABLE search_index

2. BOOLEAN MODE في WHERE = فلترة
   NATURAL LANGUAGE MODE في SELECT = scoring

3. الـ index مركّب (title, content) = لا يمكن MATCH(title) وحده

4. InnoDB فقط يدعم FULLTEXT في MySQL 5.6+
```

### الأداء

```
1. chunk(100) في الـ Reindex = أداء ممتاز للبيانات الكبيرة

2. LEFT JOIN مع data_types مطلوب للـ Intent boost
   إذا لم تكن بحاجة لـ intent → يمكن حذف الـ JOIN

3. search_index لا يدعم soft deletes
   عند حذف entry → احذف سجله من search_index يدوياً
   (سيُبنى في خطوة مستقبلية)

4. Cache ممكن إضافته على مستوى SearchEntriesAction
   لنتائج نفس الـ query
```

### الـ Queue

```
1. IndexDataEntryListener يعمل على Queue: search-indexing
   إذا أوقفت الـ worker → الفهرسة لن تعمل

2. للـ development: QUEUE_CONNECTION=sync في .env

3. للـ production:
   - استخدم Redis
   - شغّل Supervisor لإدارة الـ workers
   - راقب الـ failed_jobs table
```