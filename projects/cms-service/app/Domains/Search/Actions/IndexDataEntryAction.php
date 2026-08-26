<?php

declare(strict_types=1);

namespace App\Domains\Search\Actions;

use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Support\Indexing\SearchDocumentBuilder;
use App\Models\DataEntry;
use Illuminate\Support\Facades\Log;

/**
 * فهرسة مدخل واحد، لكل لغات مشروعه.
 */
class IndexDataEntryAction
{
    public function __construct(
        private readonly SearchIndexRepositoryInterface $repository,
        private readonly SearchDocumentBuilder $builder,
    ) {}

    public function execute(DataEntry $entry): void
    {
        $entry->loadMissing(['values', 'values.field', 'project', 'dataType']);

        /*
         | slug نوع المحتوى يُقرأ هنا ويُمرَّر إلى البنّاء.
         |
         | هذا هو العمود الذي بقي NULL في كل صفوف الفهرس، فكان كل بحث
         | مقيَّد بنوع محتوى — أو محمول على نيّة — يعيد صفر نتائج بصمت.
         */
        $dataType = $entry->dataType;
        $dataTypeSlug = $dataType === null ? '' : (string) $dataType->slug;

        if ($dataTypeSlug === '') {
            Log::warning('IndexDataEntryAction: entry has no data type slug', [
                'entry_id' => $entry->id,
            ]);
        }

        foreach ($this->supportedLanguages($entry) as $language) {
            $this->indexLanguage($entry, $language, $dataTypeSlug);
        }
    }

    // ─────────────────────────────────────────────────────────────────

    private function indexLanguage(DataEntry $entry, string $language, string $dataTypeSlug): void
    {
        try {
            $document = $this->builder->build($entry, $language, $dataTypeSlug);

            /*
             | المستند بلا نصّ لا يُفهرس، ويُحذف إن كان مفهرساً.
             |
             | الحذف ضروري لا احتياطي: مدخل أُفرغ محتواه بعد فهرسته
             | كان سيبقى في الفهرس بنصّه القديم إلى الأبد، فيظهر في
             | نتائج بحث عن كلمات لم تعد فيه.
             */
            if (! $document->isIndexable()) {
                $this->repository->deleteByEntryAndLanguage((int) $entry->id, $language);

                return;
            }

            $this->repository->upsert($document);
        } catch (\Throwable $e) {
            // فشل لغة لا يوقف بقية اللغات.
            Log::error('IndexDataEntryAction: failed to index entry', [
                'entry_id' => $entry->id,
                'language' => $language,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function supportedLanguages(DataEntry $entry): array
    {
        $languages = $entry->project?->supported_languages;

        return is_array($languages) && $languages !== []
            ? array_values(array_unique(array_map('strval', $languages)))
            : ['en'];
    }
}
