<?php

namespace App\Domains\CMS\Support;

use Illuminate\Support\Facades\Cache;

class CacheKeys
{
    const TTL_SHORT = 300;    // 5 دقائق

    const TTL_MEDIUM = 3600;   // ساعة

    const TTL_LONG = 86400;  // يوم

    /** Cache key holding the project-list version stamp. */
    const PROJECT_LIST_VERSION = 'projects:list:version';

    // ============================================
    // 🔑 Projects
    // ============================================
    public static function allProjects(): string
    {
        return 'projects:all:v'.self::projectListVersion();
    }

    /**
     * Per-user project list — a normal user only ever sees the projects they
     * own or belong to, so the list cannot share one global cache entry.
     */
    /**
     * @param  int[]  $roleProjectIds  folded in so a role grant or revocation
     *                                 cannot be served from a stale entry
     */
    public static function userProjects(
        int $userId,
        array $roleProjectIds = []
    ): string {

        sort($roleProjectIds);

        $scope = empty($roleProjectIds)
            ? 'own'
            : md5(implode(',', $roleProjectIds));

        return "projects:user:{$userId}:{$scope}:v".self::projectListVersion();
    }

    /**
     * Monotonic stamp folded into every project-list key.
     *
     * Bumping it retires every cached list at once — the alternative would be
     * enumerating one key per user on each create/update/delete, which is not
     * possible without tracking who has a warm entry.
     */
    public static function projectListVersion(): int
    {
        return (int) Cache::get(self::PROJECT_LIST_VERSION, 1);
    }

    public static function bumpProjectListVersion(): void
    {
        // add() seeds the counter when it is missing; increment() is a no-op
        // on a missing key in some stores, which would leave stale lists live.
        Cache::add(self::PROJECT_LIST_VERSION, 1);

        Cache::increment(self::PROJECT_LIST_VERSION);
    }

    public static function project(int $id): string
    {
        return "projects:{$id}";
    }

    // ============================================
    // 🔑 DataTypes
    // ============================================
    public static function dataTypes(int $projectId): string
    {
        return "project:{$projectId}:data_types";
    }

    public static function dataType(int $id): string
    {
        return "data_types:{$id}";
    }

    public static function dataTypeBySlug(string $slug, int $projectId): string
    {
        return "project:{$projectId}:data_types:slug:{$slug}";
    }

    // ============================================
    // 🔑 Fields
    // ============================================
    public static function fields(int $dataTypeId): string
    {
        return "data_type:{$dataTypeId}:fields";
    }

    // ============================================
    // 🔑 Collections
    // ============================================

    /*
     | Collection reads come in two visibilities: the public one hides inactive
     | collections, the privileged one does not. They must never share a key —
     | otherwise one manager request caches inactive rows and every visitor
     | keeps reading them for the rest of the TTL.
     |
     | Use App\Domains\CMS\Support\CollectionCache to invalidate: it forgets
     | every visibility variant of a key, and only after the transaction commits.
     */

    public static function collections(int $projectId, bool $includeInactive = false): string
    {
        return "project:{$projectId}:collections".($includeInactive ? ':all' : '');
    }

    public static function collection(int $projectId, string $slug, bool $includeInactive = false): string
    {
        return "project:{$projectId}:collections:{$slug}".($includeInactive ? ':all' : '');
    }

    public static function collectionById(int $collectionId, bool $includeInactive = false): string
    {
        return "collections:{$collectionId}".($includeInactive ? ':all' : '');
    }

    public static function collectionItems(int $collectionId): string
    {
        return "collections:{$collectionId}:items";
    }

    public static function collectionEntries(int $collectionId): string
    {
        return "collections:{$collectionId}:entries";
    }

    // ============================================
    // 🔑 Data Entries
    // ============================================
    public static function entry(int $id, string $lang = 'default'): string
    {
        return "entries:{$id}:lang:{$lang}";
    }

    public static function entryBySlug(string $slug, string $lang = 'default'): string
    {
        return "entries:slug:{$slug}:lang:{$lang}";
    }

    // ============================================
    // 🔑 Ratings
    // ============================================
    public static function ratingStats(string $type, int $id): string
    {
        return "ratings:{$type}:{$id}:stats";
    }
}
