<?php

namespace App\Domains\CMS\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache invalidation for the DataCollection feature.
 *
 * Every write action used to call Cache::forget() directly from inside the
 * closure that App\Domains\Core\Actions\Action wraps in a transaction. Two
 * things went wrong with that, and both are handled here instead:
 *
 * 1. Forgetting a key mid-transaction opens a window: a concurrent reader
 *    misses the cache, reads the still-uncommitted (i.e. old) rows and writes
 *    them back. The transaction then commits and the stale value survives the
 *    whole TTL. Invalidation therefore runs on afterCommit — which, outside a
 *    transaction, simply runs immediately.
 *
 * 2. A cached read now exists per visibility (active-only vs. including
 *    inactive), so one logical entity maps to more than one key. Forgetting a
 *    single variant leaves the other one stale.
 */
final class CollectionCache
{
    /**
     * The project's collection list.
     */
    public static function forgetList(int $projectId): void
    {
        self::forget([
            CacheKeys::collections($projectId, false),
            CacheKeys::collections($projectId, true),
        ]);
    }

    /**
     * One collection addressed by slug.
     */
    public static function forgetCollection(int $projectId, string $slug): void
    {
        self::forget([
            CacheKeys::collection($projectId, $slug, false),
            CacheKeys::collection($projectId, $slug, true),
        ]);
    }

    /**
     * Everything keyed by collection id: the collection itself, its items and
     * the derived entries payload.
     */
    public static function forgetItems(int $collectionId): void
    {
        self::forget([
            CacheKeys::collectionById($collectionId, false),
            CacheKeys::collectionById($collectionId, true),
            CacheKeys::collectionItems($collectionId),
            CacheKeys::collectionEntries($collectionId),
        ]);
    }

    /**
     * Use this after any change to a collection's items.
     *
     * The slug-addressed payload embeds the items, so clearing only the
     * id-keyed entries leaves GET /collections/{slug} serving the previous
     * ordering — or the previous membership — for the rest of the TTL. That is
     * exactly what made reordering look like it had no effect.
     */
    public static function forgetContents(int $projectId, string $slug, int $collectionId): void
    {
        self::forgetCollection($projectId, $slug);
        self::forgetItems($collectionId);
    }

    /**
     * Full invalidation for a collection: itself, its items, and the project
     * list it appears in.
     */
    public static function forgetAll(int $projectId, string $slug, int $collectionId): void
    {
        self::forgetCollection($projectId, $slug);
        self::forgetItems($collectionId);
        self::forgetList($projectId);
    }

    /**
     * @param  string[]  $keys
     */
    private static function forget(array $keys): void
    {
        DB::afterCommit(function () use ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        });
    }
}
