<?php

namespace App\Domains\E_Commerce\DTOs\Offers;

use App\Domains\E_Commerce\Requests\UpdateOfferRequest;
use Illuminate\Support\Str;

class UpdateOfferDTO
{
    public function __construct(
        public string $collectionSlug,
        public array $collectionData,
        public array $offerData,
        public int $projectId
    ) {}

    public static function fromRequest(string $collectionSlug, UpdateOfferRequest $request): self
    {
        $collectionData = [];
        $offerData = [];

        // Collection fields
        if ($request->has('name')) {
            $collectionData['name'] = $request->name;
            $collectionData['slug'] = Str::slug($request->name);
        }

        foreach (['conditions', 'conditions_logic', 'description'] as $field) {
            if ($request->has($field)) {
                $collectionData[$field] = $request->$field;
            }
        }

        // Offer fields
        foreach (
            ['offer_duration', 'benefit_type', 'benefit_config', 'start_at', 'end_at'] as $field
        ) {
            if ($request->has($field)) {
                $offerData[$field] = $request->$field;
            }
        }
        $projectId = $request->project_id;
        return new self($collectionSlug, $collectionData, $offerData, $projectId);
    }
}
