<?php

declare(strict_types=1);

namespace App\Domains\Search\Actions;

use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Support\Indexing\IndexedDocument;
use App\Domains\Search\Support\Indexing\SearchDocumentBuilder;
use App\Models\DataEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * إعادة بناء الفهرس كاملاً.
 *
 * ─── لماذا يشارك البنّاء نفسه ──────────────────────────────────────
 *
 * كان هذا الصنف يبني صفوفه بيده، مستقلّاً عن مسار الفهرسة التزايدية.
 * وقد افترق المساران فعلاً: كلاهما أغفل الأعمدة المحسوبة مسبقاً، لكن
 * بطرق مختلفة — وهو الشكل النمطي لهذا الصنف من العلل. عمود يُضاف
 * فيُملأ في مسار وينسى في الآخر، فتختلف نتيجة البحث باختلاف الطريق
 * الذي دخل منه المستند إلى الفهرس.
 *
 * البنّاء المشترك يجعل هذا مستحيلاً: مصدر واحد للصفّ، فإمّا يُملأ
 * العمود في المسارين أو في أيّهما.
 */
class ReindexSearchAction
{
    public function __construct(
        private readonly SearchIndexRepositoryInterface $repository,
        private readonly SearchDocumentBuilder $builder,
    ) {}

    /**
     * @param  callable(int $processed, int $total): void|null  $onProgress
     * @return array{indexed: int, skipped: int, total: int, documents: int}
     */
    public function execute(?callable $onProgress = null, ?int $projectId = null): array
    {
        $stats = ['indexed' => 0, 'skipped' => 0, 'total' => 0, 'documents' => 0];

        $query = DataEntry::query()
            ->where('status', 'published')
            ->whereNull('deleted_at');

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $stats['total'] = (clone $query)->count();

        if ($stats['total'] === 0) {
            return $stats;
        }

        $this->repository->clear($projectId);

        $query
            ->with(['values', 'values.field', 'project', 'dataType'])
            ->select(['id', 'data_type_id', 'project_id', 'status', 'published_at'])
            ->orderBy('id')
            ->chunk(
                (int) config('search.indexing.chunk_size', 100),
                function ($entries) use (&$stats, $onProgress) {
                    $this->processChunk($entries, $stats);

                    if ($onProgress !== null) {
                        $onProgress($stats['indexed'] + $stats['skipped'], $stats['total']);
                    }
                }
            );

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, DataEntry>  $entries
     * @param  array{indexed:int, skipped:int, total:int, documents:int}  $stats
     */
    private function processChunk($entries, array &$stats): void
    {
        $documents = [];

        foreach ($entries as $entry) {
            $built = $this->buildForEntry($entry);

            if ($built === []) {
                $stats['skipped']++;

                continue;
            }

            foreach ($built as $document) {
                $documents[] = $document;
            }

            $stats['indexed']++;
        }

        if ($documents === []) {
            return;
        }

        $this->repository->insertMany($documents);
        $stats['documents'] += count($documents);
    }

    /**
     * @return IndexedDocument[]
     */
    private function buildForEntry(DataEntry $entry): array
    {
        $dataType = $entry->dataType;
        $dataTypeSlug = $dataType === null ? '' : (string) $dataType->slug;
        $documents = [];

        foreach ($this->supportedLanguages($entry) as $language) {
            try {
                $document = $this->builder->build($entry, $language, $dataTypeSlug);

                if ($document->isIndexable()) {
                    $documents[] = $document;
                }
            } catch (\Throwable $e) {
                Log::warning('ReindexSearchAction: failed to build document', [
                    'entry_id' => $entry->id,
                    'language' => $language,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $documents;
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
