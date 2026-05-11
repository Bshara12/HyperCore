<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Offers;

use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface OfferRepositoryInterface
{
    public function create(int $collectionId, array $data): Offer;

    public function update(int $collectionId, array $data): Offer;

    public function findByCollectionId(int $collectionId): Offer;

    public function reEvaluate(int $entryId): void;

    public function getOfferDetails($collectionId): Offer;

    public function getProjectOffers($projectId): Collection;

    public function deleteOfferByCollectionId($collectionId): void;

    public function getAndActivateDueOffers(Carbon $now);

    public function getAndDeactivateExpiredOffers(Carbon $now);

    public function subscribe(int $collectionId, SubscribeDTO $dto): void;

    public function deactivateOffer(int $collectionId): void;

    public function activateOffer(int $collectionId): void;
}
