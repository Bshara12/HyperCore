<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Events\SystemLogEvent;

class EnterOfferItemsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.enterItems';
    }

    public function __construct(
        protected OfferPriceRepositoryInterface $repository
    ) {}

    public function execute($entries, $isCodeOffer)
    {
        return $this->run(function () use ($entries, $isCodeOffer) {
            foreach ($entries as $entry) {

                if ($isCodeOffer) {
                    $entry['is_applied'] = true;
                    $entry['is_code_price'] = true;
                    $this->repository->enterOfferItem($entry);

                    continue;
                }

                $lowestEntry = $this->repository->getLowestPriceItem($entry['entry_id']);

                if (! $lowestEntry) {
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
                event(new SystemLogEvent(
                    module: 'ecommerce',
                    eventType: 'enter_offer_item',
                    userId: null,
                    entityType: 'offer',
                    entityId: $entry->applied_offer_id ?? null
                ));
                $this->repository->enterOfferItem($entry);
            }
        });
    }
}
