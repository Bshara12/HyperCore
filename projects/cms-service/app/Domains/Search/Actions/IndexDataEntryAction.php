<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\IndexEntryDTO;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Support\EntryFieldsExtractor;
use App\Models\DataEntry;
use Illuminate\Support\Facades\Log;

class IndexDataEntryAction
{
    public function __construct(
        private SearchIndexRepositoryInterface $repository,
        private EntryFieldsExtractor           $extractor,
    ) {}

    /**
     * فهرسة DataEntry لكل اللغات المدعومة في المشروع
     */
    public function execute(DataEntry $entry): void
    {
        // تأكد من تحميل العلاقات المطلوبة
        $entry->loadMissing(['values', 'values.field', 'project']);

        $project           = $entry->project;
        $supportedLanguages = $this->resolveSupportedLanguages($project);

        foreach ($supportedLanguages as $language) {
            $this->indexForLanguage($entry, $language);
        }
    }

    /**
     * فهرسة لغة واحدة محددة
     */
    private function indexForLanguage(DataEntry $entry, string $language): void
    {
        try {
            $extracted = $this->extractor->extract($entry, $language);

            $dto = new IndexEntryDTO(
                entryId:     $entry->id,
                dataTypeId:  $entry->data_type_id,
                projectId:   $entry->project_id,
                language:    $language,
                title:       $extracted['title'],
                content:     $extracted['content'] ?: null,
                meta:        $extracted['meta'] ?: null,
                status:      $entry->status,
                publishedAt: $entry->published_at?->toDateTimeString(),
            );

            $this->repository->upsert($dto);

        } catch (\Throwable $e) {
            // لا نوقف العملية الكاملة إذا فشلت لغة واحدة
            Log::error('SearchIndex: failed to index entry', [
                'entry_id' => $entry->id,
                'language' => $language,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * استخراج اللغات المدعومة من إعدادات المشروع
     * الافتراضي: ['en']
     */
    private function resolveSupportedLanguages(mixed $project): array
    {
        $languages = $project?->supported_languages ?? null;

        if (is_array($languages) && count($languages) > 0) {
            return $languages;
        }

        return ['en'];
    }
}