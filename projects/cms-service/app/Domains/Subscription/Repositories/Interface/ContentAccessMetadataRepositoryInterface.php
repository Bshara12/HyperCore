<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Models\ContentAccessMetadata;

interface ContentAccessMetadataRepositoryInterface
{
    /**
     * Find the rule for a given content.
     * Always eager-loads `features` to avoid N+1.
     */
    public function findContentRule(
        string $contentType,
        int $contentId
    ): ?ContentAccessMetadata;

    /**
     * Create a content access rule.
     * Does NOT handle features – caller is responsible.
     */
    public function create(
        array $data
    ): ContentAccessMetadata;

    /**
     * Create a rule and sync its allowed features atomically.
     *
     * @param  array        $data      Fillable fields for ContentAccessMetadata
     * @param  string[]     $features  List of feature_key strings
     */
    public function createWithFeatures(
        array $data,
        array $features
    ): ContentAccessMetadata;

    /**
     * Update a rule's scalar fields and sync its allowed features.
     *
     * @param  string[]  $features  Full replacement list of feature_key strings
     */
    public function updateWithFeatures(
        ContentAccessMetadata $metadata,
        array $data,
        array $features
    ): ContentAccessMetadata;

    public function update(
        ContentAccessMetadata $metadata,
        array $data
    ): ContentAccessMetadata;

    public function disable(
        ContentAccessMetadata $metadata
    ): ContentAccessMetadata;

    public function paginate(
        ?int $projectId = null
    );

    public function findById(
        int $id
    ): ?ContentAccessMetadata;

    /**
     * Find many rules for a content type by multiple IDs.
     * Eager-loads `features` to avoid N+1.
     *
     * @return \Illuminate\Support\Collection<int, ContentAccessMetadata>  keyed by content_id
     */
    public function findManyRules(
        string $contentType,
        array $contentIds
    );
}