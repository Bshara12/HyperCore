<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Indexing;

use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use App\Domains\Search\Support\Text\UnicodeScript;
use App\Models\DataEntry;

/**
 * SearchDocumentBuilder — المصدر الوحيد لبناء صفّ الفهرس.
 *
 * ─── لماذا موضع واحد ────────────────────────────────────────────────
 *
 * كان بناء الصفّ مكرّراً في مكانين: IndexDataEntryAction للفهرسة
 * التزايدية، وReindexSearchAction لإعادة البناء الكاملة. وقد افترقا
 * فعلاً — كلاهما أهمل الأعمدة المحسوبة مسبقاً، وأهمّها data_type_slug،
 * فبقي NULL في كل صفوف الفهرس. وبما أن المستودع يفلتر عليه:
 *
 *     WHERE si.data_type_slug = ?
 *
 * فقد كان أي بحث مقيَّد بنوع محتوى يعيد صفر نتائج دائماً. لا استثناء
 * ولا رسالة خطأ — نتائج فارغة صامتة.
 *
 * التوحيد هنا يجعل هذا الصنف من الانحراف مستحيلاً بنيوياً: مسار واحد
 * لبناء الصفّ يعني أن عموداً جديداً إمّا يُملأ في كل مكان أو لا يُملأ
 * في أيّها.
 *
 * ─── ما يبنيه ───────────────────────────────────────────────────────
 *
 *   النصّ الأصلي      — للعرض والتظليل، بلا طيّ
 *   النصّ المطبَّع     — للمطابقة، بنفس دالة تطبيع الاستعلام
 *   نصّ الـ ngram      — للغات بلا مسافات، وNULL لغيرها
 *   أطوال الحقول      — مدخلات BM25، تُحسب مرّة لا عند كل بحث
 *   السمات البنيوية   — من الحقول المخصَّصة
 */
final class SearchDocumentBuilder
{
    /** أنواع الحقول التي تصلح عنواناً. */
    private const TITLE_TYPES = ['text', 'string', 'title', 'headline', 'name'];

    /** أنواع الحقول التي تصلح متناً. */
    private const CONTENT_TYPES = ['textarea', 'richtext', 'markdown', 'wysiwyg', 'html', 'longtext', 'editor'];

    /** أنواع لا معنى لفهرستها نصّاً ولا سمةً. */
    private const IGNORED_TYPES = ['image', 'file', 'media', 'gallery', 'video', 'password', 'json'];

    public function __construct(
        private readonly AttributeNormalizer $attributes,
    ) {}

    /**
     * بناء مستند الفهرس لمدخل ولغة.
     *
     * @param  string  $dataTypeSlug  slug نوع المحتوى — يُمرَّر ولا يُستنتج
     */
    public function build(DataEntry $entry, string $language, string $dataTypeSlug): IndexedDocument
    {
        $extracted = $this->extractFields($entry, $language);

        $title = $extracted['title'];
        $content = $extracted['content'];
        $meta = $extracted['meta'];

        $titleFold = TextFolder::fold($title);
        $metaText = $this->metaText($meta);

        $maxLength = (int) config('search.indexing.max_content_length', 65535);

        $contentFold = mb_substr(TextFolder::fold($content), 0, $maxLength, 'UTF-8');

        /*
         | قيم الحقول المخصَّصة في عمود مستقلّ لا مدموجةً بالمتن.
         |
         | الجدول البنيوي يخدم الشروط الدقيقة ("سنة = 2020")، أمّا
         | البحث الحرّ ("ايفون تيتانيوم") فيحتاج القيمة ضمن نصّ مفهرس.
         | وفصلها عن المتن يجعل BM25F يزنها وزنها الخاصّ: أدلّ من ورود
         | الكلمة عرضاً في فقرة، وأقلّ من ورودها في العنوان.
         */
        $metaFold = mb_substr(TextFolder::fold($metaText), 0, $maxLength, 'UTF-8');

        $combined = trim($titleFold.' '.$contentFold.' '.$metaFold);

        return new IndexedDocument(
            entryId: (int) $entry->id,
            projectId: (int) $entry->project_id,
            language: $language,
            row: [
                'entry_id' => (int) $entry->id,
                'data_type_id' => (int) $entry->data_type_id,
                'project_id' => (int) $entry->project_id,
                'language' => $language,

                // العمود الذي كان يبقى NULL فيُعطّل كل فلترة بنوع المحتوى.
                'data_type_slug' => $dataTypeSlug,

                'script' => UnicodeScript::dominant($combined),

                'title' => $title !== '' ? mb_substr($title, 0, 255, 'UTF-8') : null,
                'content' => $content !== '' ? $content : null,
                'meta' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,

                'title_fold' => mb_substr($titleFold, 0, 512, 'UTF-8'),
                'content_fold' => $contentFold !== '' ? $contentFold : null,
                'meta_fold' => $metaFold !== '' ? $metaFold : null,
                'ngram_text' => Segmenter::ngramText($combined),

                'status' => (string) $entry->status,
                'published_at' => $entry->published_at?->toDateTimeString(),

                'title_terms' => count(Segmenter::tokenize($titleFold)),
                'content_terms' => count(Segmenter::tokenize($contentFold)),
                'meta_terms' => count(Segmenter::tokenize($metaFold)),

                'title_has_numbers' => preg_match('/\d/u', $titleFold) === 1,
                'title_word_count' => count(Segmenter::tokenize($titleFold)),
                'title_length' => mb_strlen($titleFold, 'UTF-8'),
                'primary_keyword' => $this->primaryKeyword($titleFold),
            ],
            attributes: $this->attributes->fromFields($meta),
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تصنيف قيم المدخل إلى عنوان ومتن وحقول مخصَّصة.
     *
     * @return array{title: string, content: string, meta: array<string, mixed>}
     */
    private function extractFields(DataEntry $entry, string $language): array
    {
        $title = '';
        $contentParts = [];
        $meta = [];

        foreach ($entry->values as $value) {
            $field = $value->field;

            if ($field === null) {
                continue;
            }

            /*
             | الحقول غير القابلة للترجمة تُفهرس في كل اللغات.
             |
             | رقم الموديل والماركة والسعر لا تُترجم، فتُدخَل مرّة
             | واحدة بلغة واحدة. تقييد المطابقة باللغة — كما كان —
             | كان يعني اختفاءها من فهرس بقية اللغات، فيصير البحث عن
             | "iPhone 15" بالواجهة العربية بلا نتائج.
             */
            $valueLanguage = $value->language ?? $language;
            $translatable = (bool) ($field->translatable ?? true);

            if ($translatable && $valueLanguage !== $language) {
                continue;
            }

            $type = mb_strtolower(trim((string) ($field->type ?? '')), 'UTF-8');

            if (in_array($type, self::IGNORED_TYPES, true)) {
                continue;
            }

            $raw = $value->value;

            if ($raw === null || $raw === '') {
                continue;
            }

            if ($title === '' && in_array($type, self::TITLE_TYPES, true)) {
                $title = $this->clean((string) $raw);

                continue;
            }

            if (in_array($type, self::CONTENT_TYPES, true)) {
                $contentParts[] = $this->clean((string) $raw);

                continue;
            }

            $meta[(string) $field->name] = $raw;
        }

        return [
            'title' => $title,
            'content' => trim(implode(' ', array_filter($contentParts))),
            'meta' => $meta,
        ];
    }

    /**
     * قيم الحقول المخصَّصة كنصّ واحد.
     *
     * أسماء الحقول مستبعدة عمداً: قيمة "Black" هي ما يبحث عنه المستخدم،
     * أمّا كلمة "Color" فترد في كل منتج فلا تميّز شيئاً — وإدراجها يضخّم
     * الطول فيعاقب BM25 المستندَ الغنيَّ بالحقول.
     *
     * @param  array<string, mixed>  $meta
     */
    private function metaText(array $meta): string
    {
        $parts = [];

        foreach ($meta as $value) {
            if (is_scalar($value)) {
                $parts[] = (string) $value;

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return trim(implode(' ', $parts));
    }

    private function clean(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function primaryKeyword(string $titleFold): ?string
    {
        $tokens = Segmenter::tokenize($titleFold);

        return $tokens === [] ? null : mb_substr($tokens[0], 0, 100, 'UTF-8');
    }
}
