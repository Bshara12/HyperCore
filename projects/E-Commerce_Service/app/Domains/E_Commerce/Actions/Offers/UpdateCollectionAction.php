<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Services\CMS\CMSApiClient;

class UpdateCollectionAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.updateCollcetion';
    }

    public function __construct(
        protected CMSApiClient $cms,
    ) {}

    public function execute($dto)
    {
        return $this->run(function () use ($dto) {
            return $this->cms->updateCollection($dto->collectionSlug, $dto->collectionData);
        });
    }
}
