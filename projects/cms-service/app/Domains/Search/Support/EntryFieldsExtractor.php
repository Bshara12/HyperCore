<?php

namespace App\Domains\Search\Support;

use App\Models\DataEntry;

class EntryFieldsExtractor
{
    /**
     * الأنواع التي تُعتبر "title" (أعلى وزن)
     */
    private const TITLE_TYPES = ['text', 'string', 'title'];

    /**
     * الأنواع التي تُعتبر "content" (وزن متوسط)
     */
    private const CONTENT_TYPES = ['textarea', 'richtext', 'markdown', 'wysiwyg'];

    /**
     * استخراج البيانات من DataEntry مع قيمه لتخزينها في search_index
     *
     * @return array{title: ?string, content: ?string, meta: array}
     */
    public function extract(DataEntry $entry, string $language): array
    {
        $title = null;
        $content = null;
        $meta = [];

        // entry->values هي علاقة محملة مسبقاً
        foreach ($entry->values as $value) {
            $field = $value->field;         // العلاقة مع data_type_fields
            $fieldType = strtolower($field->type ?? '');
            $lang = $value->language ?? 'en';

            // نتجاهل اللغات الأخرى
            if ($lang !== $language) {
                continue;
            }

            $rawValue = $value->value;

            if (empty($rawValue)) {
                continue;
            }

            if (in_array($fieldType, self::TITLE_TYPES, true) && $title === null) {
                // أول حقل نصي قصير = العنوان
                $title = $this->cleanText($rawValue);
            } elseif (in_array($fieldType, self::CONTENT_TYPES, true)) {
                // الحقول الطويلة تُضاف للمحتوى
                $content .= ' '.$this->cleanText($rawValue);
            } else {
                // باقي الحقول تذهب للـ meta
                $meta[$field->name] = $rawValue;
            }
        }

        return [
            'title' => $title,
            'content' => trim($content ?? ''),
            'meta' => $meta,
        ];
    }

    /**
     * تنظيف النص من HTML وإزالة المسافات الزائدة
     */
    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
