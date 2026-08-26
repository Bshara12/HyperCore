<?php

declare(strict_types=1);

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Domains\Search\Support\Indexing\IndexedDocument;
use Illuminate\Support\Facades\DB;

/**
 * كتابة الفهرس.
 *
 * ─── لماذا كل كتابة داخل معاملة ────────────────────────────────────
 *
 * صفّ الفهرس وصفوف سماته وجهان لشيء واحد. كتابة أحدهما دون الآخر
 * تُنتج حالتين خاطئتين:
 *
 *   صفّ بلا سمات  — الشروط البنيوية تُقصيه، فيختفي مستند موجود.
 *   سمات بلا صفّ  — صفوف يتيمة تنمو بلا حدّ وتُبطئ استعلامات EXISTS.
 *
 * ─── ما كان مفقوداً ────────────────────────────────────────────────
 *
 * الإصدار السابق كان يكتب تسعة أعمدة فقط عبر updateOrCreate، تاركاً
 * كل الأعمدة المحسوبة مسبقاً على قيمها الافتراضية. وأهمّها
 * data_type_slug الذي يفلتر عليه المستودع — فبقي NULL في كل صفّ،
 * وصار كل بحث مقيَّد بنوع محتوى يعيد صفر نتائج بلا خطأ ظاهر.
 *
 * الكتابة هنا تمرّ بصفّ يبنيه SearchDocumentBuilder كاملاً، فلا
 * يعود ممكناً أن يُنسى عمود.
 */
class EloquentSearchIndexRepository implements SearchIndexRepositoryInterface
{
    public function upsert(IndexedDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $timestamp = now()->toDateTimeString();

            DB::table('search_indices')->updateOrInsert(
                [
                    'entry_id' => $document->entryId,
                    'language' => $document->language,
                ],
                [...$document->row, 'updated_at' => $timestamp, 'created_at' => $timestamp]
            );

            $this->replaceAttributes($document, $timestamp);
        });
    }

    /**
     * إدراج مجمَّع لدفعة كاملة.
     *
     * @param  IndexedDocument[]  $documents
     */
    public function insertMany(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        DB::transaction(function () use ($documents) {
            $timestamp = now()->toDateTimeString();

            $rows = [];
            $attributeRows = [];

            foreach ($documents as $document) {
                $rows[] = [...$document->row, 'created_at' => $timestamp, 'updated_at' => $timestamp];

                foreach ($document->attributeRows($timestamp) as $attributeRow) {
                    $attributeRows[] = $attributeRow;
                }
            }

            DB::table('search_indices')->insert($rows);

            /*
             | السمات تُدرَج على شرائح.
             |
             | مستند بعشرين حقلاً في دفعة من مئة يعني ألفَي صفّ في
             | عبارة واحدة، وقد تتجاوز max_allowed_packet فتفشل الدفعة
             | كلها. التشريح يجعل الحجم محكوماً مهما كثرت الحقول.
             */
            foreach (array_chunk($attributeRows, 500) as $chunk) {
                DB::table('search_index_attributes')->insert($chunk);
            }
        });
    }

    public function deleteByEntryId(int $entryId): void
    {
        DB::transaction(function () use ($entryId) {
            DB::table('search_index_attributes')->where('entry_id', $entryId)->delete();
            DB::table('search_indices')->where('entry_id', $entryId)->delete();
        });
    }

    public function deleteByEntryAndLanguage(int $entryId, string $language): void
    {
        DB::transaction(function () use ($entryId, $language) {
            DB::table('search_index_attributes')
                ->where('entry_id', $entryId)
                ->where('language', $language)
                ->delete();

            DB::table('search_indices')
                ->where('entry_id', $entryId)
                ->where('language', $language)
                ->delete();
        });
    }

    public function existsForEntry(int $entryId, string $language): bool
    {
        return DB::table('search_indices')
            ->where('entry_id', $entryId)
            ->where('language', $language)
            ->exists();
    }

    /**
     * تفريغ فهرس مشروع، أو الفهرس كلّه.
     */
    public function clear(?int $projectId = null): void
    {
        DB::transaction(function () use ($projectId) {
            $attributes = DB::table('search_index_attributes');
            $indices = DB::table('search_indices');

            if ($projectId !== null) {
                $attributes->where('project_id', $projectId);
                $indices->where('project_id', $projectId);
            }

            $attributes->delete();
            $indices->delete();
        });
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * استبدال سمات مستند: حذف ثم إدراج.
     *
     * الاستبدال لا التحديث، لأن إعادة الفهرسة قد تُسقط حقلاً حذفه
     * صاحب المحتوى. التحديث وحده كان سيُبقي السمة القديمة إلى الأبد،
     * فتظلّ الشروط البنيوية تطابق قيمة لم تعد موجودة.
     */
    private function replaceAttributes(IndexedDocument $document, string $timestamp): void
    {
        DB::table('search_index_attributes')
            ->where('entry_id', $document->entryId)
            ->where('language', $document->language)
            ->delete();

        $rows = $document->attributeRows($timestamp);

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('search_index_attributes')->insert($chunk);
        }
    }
}
