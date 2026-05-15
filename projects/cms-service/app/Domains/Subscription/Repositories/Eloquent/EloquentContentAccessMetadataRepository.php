<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Models\ContentAccessFeature;
use App\Models\ContentAccessMetadata;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentContentAccessMetadataRepository
    implements ContentAccessMetadataRepositoryInterface
{
    // ─── Queries ──────────────────────────────────────────────────────

    public function findContentRule(
        string $contentType,
        int $contentId
    ): ?ContentAccessMetadata {

        return ContentAccessMetadata::query()
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('is_active', true)
            ->with('features')          // ← eager load; no N+1
            ->first();
    }

    public function findById(
        int $id
    ): ?ContentAccessMetadata {

        return ContentAccessMetadata::query()
            ->with('features')
            ->find($id);
    }

    public function paginate(
        ?int $projectId = null
    ) {
        return ContentAccessMetadata::query()
            ->when(
                $projectId,
                fn($q) => $q->where('project_id', $projectId)
            )
            ->with('features')
            ->latest()
            ->paginate(20);
    }

    public function findManyRules(
        string $contentType,
        array $contentIds
    ) {
        return ContentAccessMetadata::query()
            ->where('content_type', $contentType)
            ->whereIn('content_id', $contentIds)
            ->where('is_active', true)
            ->with('features')          // ← eager load; no N+1
            ->get()
            ->keyBy('content_id');
    }

    // ─── Writes ───────────────────────────────────────────────────────

    /**
     * Low-level create (no features).
     * Kept for backward-compat; prefer createWithFeatures.
     */
    public function create(
        array $data
    ): ContentAccessMetadata {

        return ContentAccessMetadata::create($data);
    }

    /**
     * Create rule + sync features atomically.
     */
    public function createWithFeatures(
        array $data,
        array $features
    ): ContentAccessMetadata {

        return DB::transaction(function () use ($data, $features) {

            $metadata = ContentAccessMetadata::create($data);

            $this->syncFeatures($metadata, $features);

            return $metadata->load('features');
        });
    }

    /**
     * Update scalar fields + sync features atomically.
     */
    public function updateWithFeatures(
        ContentAccessMetadata $metadata,
        array $data,
        array $features
    ): ContentAccessMetadata {

        return DB::transaction(function () use ($metadata, $data, $features) {

            $metadata->update($data);

            $this->syncFeatures($metadata, $features);

            return $metadata->fresh(['features']);
        });
    }

    /**
     * Low-level update (no feature sync).
     * Kept for activate/disable flows.
     */
    public function update(
        ContentAccessMetadata $metadata,
        array $data
    ): ContentAccessMetadata {

        $metadata->update($data);

        return $metadata->fresh(['features']);
    }

    public function disable(
        ContentAccessMetadata $metadata
    ): ContentAccessMetadata {

        $metadata->update(['is_active' => false]);

        return $metadata->fresh(['features']);
    }

    // ─── Private helpers ──────────────────────────────────────────────

    /**
     * Full-replace sync: delete existing, bulk-insert new ones.
     * Skips empty-string / null values silently.
     *
     * @param  string[]  $features
     */
    private function syncFeatures(
        ContentAccessMetadata $metadata,
        array $features
    ): void {

        // Delete existing features for this rule
        ContentAccessFeature::where(
            'content_access_metadata_id',
            $metadata->id
        )->delete();

        $features = array_values(
            array_unique(
                array_filter($features, fn($f) => is_string($f) && $f !== '')
            )
        );

        if (empty($features)) {
            return;
        }

        $rows = array_map(fn($key) => [
            'content_access_metadata_id' => $metadata->id,
            'feature_key'                => $key,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ], $features);

        ContentAccessFeature::insert($rows);
    }
}