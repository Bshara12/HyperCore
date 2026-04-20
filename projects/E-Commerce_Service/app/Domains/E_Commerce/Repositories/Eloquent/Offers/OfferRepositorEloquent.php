<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Offers;

use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Models\Offer;
use App\Models\OfferPrice;
use App\Models\UserOffer;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class OfferRepositorEloquent implements OfferRepositoryInterface
{
  public function create(int $collectionId, array $data): Offer
  {
    $data['collection_id'] = $collectionId;
    return Offer::create($data);
  }

  public function update(int $collectionId, array $data): Offer
  {
    $offer = Offer::where('collection_id', $collectionId)->firstOrFail();
    $offer->update($data);
    return $offer;
  }

  public function findByCollectionId(int $collectionId): Offer
  {
    return Offer::where('collection_id', $collectionId)->firstOrFail();
  }

  public function reEvaluate(int $entryId): void
  {
    OfferPrice::where('entry_id', $entryId)
      ->where('is_code_price', false)
      ->update(['is_applied' => false]);

    OfferPrice::where('entry_id', $entryId)
      ->where('is_code_price', false)
      ->orderBy('final_price')
      ->limit(1)
      ->update(['is_applied' => true]);
  }

  public function getOfferDetails($collectionId): Offer
  {
    return Offer::where('collection_id', $collectionId)->first();
  }

  public function getProjectOffers($projectId): Collection
  {
    return Offer::where('project_id', $projectId)->get();
  }

  public function deleteOfferByCollectionId($collectionId): void
  {
    $deleted = Offer::onlyTrashed()
      ->where('collection_id', $collectionId)
      ->first();
    if ($deleted) {
      throw new DomainException("This offer was deleted previously");
    }
    $offer = Offer::where('collection_id', $collectionId)->first();
    $offer->update(['is_active' => false]);
    $offer->delete();
  }

  public function deactivateOffer($collectionId): void
  {
    $offer = Offer::where('collection_id', $collectionId)
      ->where('is_active', true)
      ->first();

    if ($offer) {
      $offer->update([
        'is_active' => false
      ]);
    }
  }

  public function activateOffer($collectionId): void
  {
    $offer = Offer::where('collection_id', $collectionId)
      ->first();

    if ($offer) {
      $offer->update([
        'is_active' => true
      ]);
    }
  }

  public function getAndActivateDueOffers(Carbon $now): array
  {
    $offers = Offer::where('is_active', false)
      ->whereNotNull('start_at')
      ->where('start_at', '<=', $now)
      ->get();

    $entryIds = [];

    foreach ($offers as $offer) {
      $offer->update(['is_active' => true]);

      $ids = OfferPrice::where('applied_offer_id', $offer->id)
        ->pluck('entry_id')
        ->toArray();

      $entryIds = array_merge($entryIds, $ids);
    }

    return $entryIds;
  }

  public function getAndDeactivateExpiredOffers(Carbon $now): array
  {
    $offers = Offer::where('is_active', true)
      ->whereNotNull('end_at')
      ->where('end_at', '<=', $now)
      ->get();

    $entryIds = [];

    foreach ($offers as $offer) {
      $offer->update(['is_active' => false]);

      $ids = OfferPrice::where('applied_offer_id', $offer->id)
        ->pluck('entry_id')
        ->toArray();

      $entryIds = array_merge($entryIds, $ids);

      OfferPrice::where('applied_offer_id', $offer->id)->delete();
    }

    return $entryIds;
  }

  public function subscribe(int $collectionId, SubscribeDTO $dto): void
  {
    $offer = Offer::where('collection_id', $collectionId)->first();
    if (!$offer) {
      throw new DomainException("Offer doesn't exist");
    }
    if ($offer->code != $dto->code) {
      throw new DomainException("Invalid or expired code");
    }
    $subscribed = UserOffer::where('offer_id', $offer->id)->where('user_id', $dto->user_id)->first();

    if ($subscribed)
      throw new DomainException("This offer has already been subscribed to");
    else
      UserOffer::create([
        'offer_id'   => $offer->id,
        'user_id'    => $dto->user_id,
        'project_id' => $dto->project_id,
        'start_at'   => Carbon::now(),
        'end_at'     => Carbon::now()->addDays($offer->offer_duration),
      ]);
  }
}
