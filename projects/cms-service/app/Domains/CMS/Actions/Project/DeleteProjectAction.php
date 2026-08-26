<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class DeleteProjectAction
{
    public function __construct(
        private ProjectRepositoryInterface $repository
    ) {}

    public function execute(Project $project): void
    {
        event(new SystemLogEvent(
            module: 'cms',
            eventType: 'audit',
            userId: $project->owner_id,
            entityType: 'delete project',
            entityId: $project->id,
            oldValues: $project->toArray(),
            newValues: ['deleted']
        ));

        // نجمع مفاتيح الكاش قبل الحذف لأن الصفوف قد لا تبقى متاحة بعده
        $keys = $this->cacheKeysFor($project);

        $this->repository->delete($project);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        CacheKeys::bumpProjectListVersion();
    }

    /**
     * كل مفاتيح الكاش الخاصة بالمشروع وبكل ما يتفرّع عنه
     * (أنواع البيانات، الحقول، الـ collections، والـ entries).
     *
     * @return array<int, string>
     */
    private function cacheKeysFor(Project $project): array
    {
        $projectId = $project->id;

        // The project lists are retired via the version stamp, not by key.
        $keys = [
            CacheKeys::project($projectId),
            CacheKeys::dataTypes($projectId),
            CacheKeys::collections($projectId),
        ];

        $dataTypes = DataType::withTrashed()
            ->where('project_id', $projectId)
            ->get(['id', 'slug']);

        foreach ($dataTypes as $dataType) {
            $keys[] = CacheKeys::dataType($dataType->id);
            $keys[] = CacheKeys::dataTypeBySlug($dataType->slug, $projectId);
            $keys[] = CacheKeys::fields($dataType->id);
        }

        $collections = DataCollection::where('project_id', $projectId)
            ->get(['id', 'slug']);

        foreach ($collections as $collection) {
            $keys[] = CacheKeys::collection($projectId, $collection->slug);
            $keys[] = CacheKeys::collectionById($collection->id);
            $keys[] = CacheKeys::collectionItems($collection->id);
            $keys[] = CacheKeys::collectionEntries($collection->id);
        }

        $entries = DataEntry::withTrashed()
            ->where('project_id', $projectId)
            ->get(['id', 'slug']);

        $languages = array_unique(array_merge(
            ['default'],
            (array) ($project->supported_languages ?? []),
            ['ar', 'en', 'fr']
        ));

        foreach ($entries as $entry) {
            foreach ($languages as $lang) {
                $keys[] = CacheKeys::entry($entry->id, $lang);

                if (! empty($entry->slug)) {
                    $keys[] = CacheKeys::entryBySlug($entry->slug, $lang);
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
