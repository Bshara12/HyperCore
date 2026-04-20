<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Benefits\BenefitStrategyFactory;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class CalculatePricesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.calculatePrices';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected BenefitStrategyFactory $benefitFactory,
    protected OfferPriceRepositoryInterface $repository
  ) {}

  public function execute(array $data)
  {
    return $this->run(function () use ($data) {
      $strategy = $this->benefitFactory->make($data['offer']['benefit_type']);
      $entries = $this->cms->getDynamicEntries($data['collection']['slug']);

      $calculated = [];
      foreach ($entries as $entry) {
        $newPrice = $strategy->calculate(
          $entry['price'],
          1,
          $data['offer']['benefit_config']
        );

        $entryPrice = $this->repository->getEntryPrice($entry['id'], $data['offer']['id']);
        if ($entryPrice) {
          if ($newPrice == $entryPrice->final_price) {
            continue;
          }
          $this->repository->deleteOfferPrice($entryPrice->id);
        }


        $calculated[] = [
          'entry_id' => $entry['id'],
          'applied_offer_id' => $data['offer']['id'],
          'original_price' => $entry['price'],
          'final_price' => $newPrice,
        ];
      }

      foreach ($calculated as $entry) {

        if ($data['offer']['is_code_offer']) {
          $entry['is_applied'] = true;
          $entry['is_code_price'] = true;
          $this->repository->enterOfferItem($entry);
          continue;
        }

        $lowestEntry = $this->repository->getLowestPriceItem($entry['entry_id']);

        if (!$lowestEntry) {
          $entry['is_applied'] = true;
          $this->repository->enterOfferItem($entry);
          continue;
        }

        if ($entry['final_price'] < $lowestEntry->final_price) {
          $entry['is_applied'] = true;
          $this->repository->disableItemPrice($entry['entry_id']);
        } else {
          $entry['is_applied'] = false;
        }

        $this->repository->enterOfferItem($entry);
      }

      return $calculated;
    });
  }
}
