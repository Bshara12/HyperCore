<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Support\EntryFieldsExtractor;
use App\Models\DataEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReindexSearchAction
{
    /**
     * حجم الـ chunk - عدد الـ entries التي تُعالج دفعة واحدة
     * 100 هو الأنسب: لا يُثقل الذاكرة ولا يُكثّر الـ queries
     */
    private const CHUNK_SIZE = 100;

    public function __construct(
        private SearchIndexRepositoryInterface $repository,
        private EntryFieldsExtractor           $extractor,
    ) {}

    /**
     * نقطة الدخول الرئيسية
     *
     * @param  callable(int $processed, int $total): void  $onProgress
     * @return array{indexed: int, skipped: int, total: int}
     */
    public function execute(?callable $onProgress = null): array
    {
        $stats = [
            'indexed' => 0,
            'skipped' => 0,
            'total'   => 0,
        ];

        // ─── 1. احسب العدد الكلي (للـ progress bar) ─────────────────
        $stats['total'] = DataEntry::where('status', 'published')
            ->whereNull('deleted_at')
            ->count();

        if ($stats['total'] === 0) {
            return $stats;
        }

        // ─── 2. نظّف الجدول بشكل آمن ────────────────────────────────
        $this->clearIndex();

        // ─── 3. اجلب الـ entries على دفعات ───────────────────────────
        DataEntry::query()
            ->with([
                'values',
                'values.field',  // لتحديد نوع الحقل (title/content/meta)
                'project',       // لمعرفة supported_languages
            ])
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->select([         // لا تجلب كل الأعمدة لتوفير الذاكرة
                'id',
                'data_type_id',
                'project_id',
                'status',
                'published_at',
            ])
            ->orderBy('id')    // ترتيب ثابت لضمان consistency في الـ chunks
            ->chunk(self::CHUNK_SIZE, function ($entries) use (&$stats, $onProgress) {
                $this->processChunk($entries, $stats);

                if ($onProgress !== null) {
                    ($onProgress)($stats['indexed'] + $stats['skipped'], $stats['total']);
                }
            });

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * معالجة دفعة واحدة من الـ entries
     * كل دفعة تُدرج في transaction واحدة لتحسين الأداء
     */
    private function processChunk($entries, array &$stats): void
    {
        // جمع كل الـ rows التي سنُدرجها في هذه الدفعة
        $rows = [];

        foreach ($entries as $entry) {
            $entryRows = $this->buildIndexRows($entry);

            if (empty($entryRows)) {
                $stats['skipped']++;
                continue;
            }

            foreach ($entryRows as $row) {
                $rows[] = $row;
            }

            $stats['indexed']++;
        }

        if (empty($rows)) {
            return;
        }

        // ─── Bulk Insert بدلاً من insert واحد لكل entry ───────────
        // أسرع بكثير: 100 entry = query واحد بدل 100 query
        DB::table('search_indices')->insert($rows);
    }

    /**
     * بناء rows الفهرسة لـ entry واحد (row لكل لغة)
     *
     * @return array[]
     */
    private function buildIndexRows(DataEntry $entry): array
    {
        $languages = $this->resolveSupportedLanguages($entry->project);
        $rows      = [];
        $now       = now()->toDateTimeString();

        foreach ($languages as $language) {
            try {
                $extracted = $this->extractor->extract($entry, $language);

                // تجاهل إذا لم يكن هناك محتوى قابل للفهرسة
                if (empty($extracted['title'])) {
                    continue;
                }

                $rows[] = [
                    'entry_id'     => $entry->id,
                    'data_type_id' => $entry->data_type_id,
                    'project_id'   => $entry->project_id,
                    'language'     => $language,
                    'title'        => $extracted['title'],
                    'content'      => $extracted['content'] ?: null,
                    'meta'         => !empty($extracted['meta'])
                        ? json_encode($extracted['meta'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'status'       => $entry->status,
                    'published_at' => $entry->published_at?->toDateTimeString(),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

            } catch (\Throwable $e) {
                Log::warning('ReindexSearch: failed to build row', [
                    'entry_id' => $entry->id,
                    'language' => $language,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $rows;
    }

    /**
     * تفريغ الجدول بدون حذف الـ FULLTEXT index
     *
     * TRUNCATE أسرع من DELETE لكنه يحتاج إذن خاص في بعض الـ setups
     * لذلك نستخدم DELETE مع إمكانية الـ fallback
     */
    private function clearIndex(): void
    {
        DB::table('search_indices')->delete();

        // إعادة تعيين الـ AUTO_INCREMENT اختياري لكن ينظّف الجدول
        DB::statement('ALTER TABLE search_indices AUTO_INCREMENT = 1');
    }

    /**
     * استخراج اللغات المدعومة للمشروع
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