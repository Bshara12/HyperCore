<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Domains\Subscription\Repositories\Interface\ContentTypeResolverInterface;
use App\Exceptions\ContentEntryNotFoundException;
use Illuminate\Support\Facades\DB;

class DataEntryContentTypeResolver implements ContentTypeResolverInterface
{
    /**
     * Resolve the data_type slug for a given DataEntry ID.
     *
     * Uses a single JOIN query — no Eloquent model loading overhead.
     *
     * Query plan:
     *   data_entries (PK lookup) → JOIN data_types (PK lookup) → return slug
     *
     * @throws ContentEntryNotFoundException
     */
    public function resolve(int $contentId): string
    {
        $slug = DB::table('data_entries')
            ->join(
                'data_types',
                'data_entries.data_type_id',
                '=',
                'data_types.id'
            )
            ->where('data_entries.id', $contentId)
            ->whereNull('data_entries.deleted_at')
            ->value('data_types.slug');

        if ($slug === null) {
            throw new ContentEntryNotFoundException($contentId);
        }

        return $slug;
    }
}