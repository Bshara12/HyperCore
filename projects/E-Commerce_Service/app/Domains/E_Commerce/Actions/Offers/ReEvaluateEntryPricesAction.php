<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class ReEvaluateEntryPricesAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.reEvalutePrices';
    }

    public function __construct(
        protected CMSApiClient $cms,
        protected OfferRepositoryInterface $repository
    ) {}

    public function execute(array $entries)
    {
        $this->run(function () use ($entries) {
            foreach ($entries as $entry) {
                $this->repository->reEvaluate($entry['entry_id']);
            }
        });
    }
}
