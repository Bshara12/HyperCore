<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Indexing;

/**
 * IndexedDocument — مستند جاهز للكتابة في الفهرس.
 *
 * يحمل صفّ search_indices وصفوف search_index_attributes معاً لأنهما
 * يجب أن يُكتبا ويُحذفا معاً: صفّ فهرس بلا سماته يعني أن الشروط
 * البنيوية تُقصيه، وسمات بلا صفّها يعني صفوفاً يتيمة تنمو بلا حدّ.
 */
final readonly class IndexedDocument
{
    /**
     * @param  array<string, mixed>  $row  صفّ search_indices
     * @param  array<int, array{key:string, value_text:?string, value_num:?float}>  $attributes
     */
    public function __construct(
        public int $entryId,
        public int $projectId,
        public string $language,
        public array $row,
        public array $attributes = [],
    ) {}

    /**
     * صفوف السمات جاهزة للإدراج، بعد إلحاق مفاتيح الربط والطوابع.
     *
     * @return array<int, array<string, mixed>>
     */
    public function attributeRows(string $timestamp): array
    {
        return array_map(
            fn (array $attribute): array => [
                'entry_id' => $this->entryId,
                'project_id' => $this->projectId,
                'language' => $this->language,
                'attr_key' => $attribute['key'],
                'value_text' => $attribute['value_text'],
                'value_num' => $attribute['value_num'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $this->attributes
        );
    }

    /**
     * هل يستحقّ المستند الفهرسة؟
     *
     * مستند بلا عنوان ولا متن لا يمكن أن يطابق أي بحث نصّي، وإدراجه
     * يضخّم الفهرس ويشوّه إحصاءات المتن التي يقوم عليها BM25.
     */
    public function isIndexable(): bool
    {
        return ($this->row['title_fold'] ?? '') !== ''
            || ($this->row['content_fold'] ?? '') !== '';
    }
}
