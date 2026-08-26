<?php

declare(strict_types=1);

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\Support\Indexing\IndexedDocument;

/**
 * كتابة الفهرس.
 *
 * الواجهة تتلقّى IndexedDocument لا DTO مسطَّحاً: المستند يحمل صفّه
 * وسماته معاً، فيستحيل على متصل أن يكتب أحدهما وينسى الآخر.
 */
interface SearchIndexRepositoryInterface
{
    public function upsert(IndexedDocument $document): void;

    /**
     * @param  IndexedDocument[]  $documents
     */
    public function insertMany(array $documents): void;

    public function deleteByEntryId(int $entryId): void;

    public function deleteByEntryAndLanguage(int $entryId, string $language): void;

    public function existsForEntry(int $entryId, string $language): bool;

    public function clear(?int $projectId = null): void;
}
