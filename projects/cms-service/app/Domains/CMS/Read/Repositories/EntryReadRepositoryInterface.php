<?php

namespace App\Domains\CMS\Read\Repositories;

interface EntryReadRepositoryInterface
{
    /**
     * Find a published entry with its translated values and SEO.
     *
     * @param int $entryId
     * @param string $language
     * @param string $fallback
     * @return array|null
     */
    public function findPublishedWithValues(
        int $entryId,
        string $language,
        string $fallback
    ): ?array;
}