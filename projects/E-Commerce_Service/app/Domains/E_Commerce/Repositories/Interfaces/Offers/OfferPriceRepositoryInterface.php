<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Offers;

interface OfferPriceRepositoryInterface
{
  public function enterOfferItem(array $data);

  public function getLowestPriceItem($entryId);

  public function disableItemPrice($entryId);

  public function deleteOfferPricesForOffer($offerId);

  public function deleteOfferPrice($offerPriceId): void;

  public function deleteOfferPriceForEntryAndProject(int $entryId, int $offerId): void;

  public function getEntryPrice(int $entryId, int $offerId);

  // test
    public function getAutomaticPrices(array $entryIds);
    public function getCodePrices(array $entryIds, string $code);

    // public function getUserPrices(array $entryIds, int $userId);
    public function getUserPrices(array $entryIds);
}
