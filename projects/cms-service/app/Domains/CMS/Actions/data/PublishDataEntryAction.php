<?php

namespace App\Domains\CMS\Actions\Data;

use App\Models\DataEntry;
use App\Support\CurrentProject;
use Illuminate\Support\Carbon;

class PublishDataEntryAction
{
    public function execute(string $entrySlug, ?int $userId)
    {
        $projectId = CurrentProject::id();

        $entry = DataEntry::query()
            ->where('project_id', $projectId)
            ->where('slug', $entrySlug)
            ->firstOrFail();

        $now = now();
        $publishedAt = $entry->published_at ? Carbon::parse($entry->published_at) : null;

        $entry->status = 'published';

        if ($publishedAt === null || $publishedAt->greaterThan($now)) {
            $entry->published_at = $now;
        }

        if ($userId !== null) {
            if (in_array('updated_by', $entry->getFillable(), true) || array_key_exists('updated_by', $entry->getAttributes())) {
                $entry->updated_by = $userId;
            }
        }

        $entry->save();

        return $entry;
    }
}
