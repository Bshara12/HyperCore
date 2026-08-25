<?php

namespace App\Domains\CMS\DTOs\DataCollection;

use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;
use App\Support\CurrentProject;

class UpdateDataCollectionDTO
{
    public function __construct(
        public int $collection_id,
        public int $project_id,
        public string $slug,
        public array $data
    ) {}

    public static function fromRequest(UpdateDataCollectionRequest $request, string $collectionSlug): self
    {
        $projectId = CurrentProject::id();

        // Scoped to the current project: slugs are only unique per project
        // (unique index is project_id + slug), so an unscoped lookup resolves to
        // whichever project created that slug first.
        $collection = DataCollection::where('project_id', $projectId)
            ->where('slug', $collectionSlug)
            ->firstOrFail();

        /*
         | The slug is intentionally NOT derived from the name any more.
         |
         | It used to be rewritten on every rename, which silently broke every
         | external reference: E-Commerce stores offers by collection_slug, the
         | cache is keyed by slug, and the route itself is the slug. Renaming a
         | collection is a label change; it must not re-address the resource.
         */
        $data = $request->only([
            'name',
            'conditions',
            'conditions_logic',
            'description',
            'settings',
            'is_active',
        ]);

        return new self(
            collection_id: $collection->id,
            project_id: $projectId,
            slug: $collection->slug,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
