<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Actions\Offers\ActivateOfferAction;
use App\Domains\E_Commerce\Actions\Offers\CalculatePricesAction;
use App\Domains\E_Commerce\Actions\Offers\CreateOfferAction;
use App\Domains\E_Commerce\Actions\Offers\DeactivateOfferAction;
use App\Domains\E_Commerce\Actions\Offers\DeleteOfferAction;
use App\Domains\E_Commerce\Actions\Offers\DeleteOfferPricesAction;
use App\Domains\E_Commerce\Actions\Offers\EnterOfferItemsAction;
use App\Domains\E_Commerce\Actions\Offers\InsertOfferItemsAction;
use App\Domains\E_Commerce\Actions\Offers\ProcessScheduledOffersAction;
use App\Domains\E_Commerce\Actions\Offers\ReEvaluateEntryPricesAction;
use App\Domains\E_Commerce\Actions\Offers\RemoveOfferItemsAction;
use App\Domains\E_Commerce\Actions\Offers\SubscribeAction;
use App\Domains\E_Commerce\Actions\Offers\UpdateCollectionAction;
use App\Domains\E_Commerce\Actions\Offers\UpdateOfferAction;
use App\Domains\E_Commerce\DTOs\Offers\ActivationOfferDTO;
use App\Domains\E_Commerce\DTOs\Offers\CreateOfferDTO;
use App\Domains\E_Commerce\DTOs\Offers\OfferItemsDTO;
use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Domains\E_Commerce\DTOs\Offers\UpdateOfferDTO;
use App\Domains\E_Commerce\Read\Actions\Offers\IndexOffersAction;
use App\Domains\E_Commerce\Read\Actions\Offers\ShowOfferDetailsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class OfferService
{
    public function __construct(
        protected CMSApiClient $cms,
        protected CreateOfferAction $createAction,
        protected CalculatePricesAction $calculateAction,
        protected EnterOfferItemsAction $enterItemsAction,
        protected UpdateCollectionAction $updateCollection,
        protected UpdateOfferAction $updateOffer,
        protected OfferRepositoryInterface $repository,
        protected DeleteOfferPricesAction $deleteOfferPricesAction,
        protected ReEvaluateEntryPricesAction $reEvaluateAction,
        protected ShowOfferDetailsAction $showDetailsAction,
        protected IndexOffersAction $indexAction,
        protected DeleteOfferAction $deleteOffer,
        protected InsertOfferItemsAction $insertItemsAction,
        protected RemoveOfferItemsAction $removeItemsAction,
        protected DeactivateOfferAction $deactivateOffer,
        protected ActivateOfferAction $activateOffer,
        protected ProcessScheduledOffersAction $action,
        protected SubscribeAction $subscribeAction
    ) {}

    public function create(CreateOfferDTO $dto): void
    {
        $data = $this->createAction->execute($dto);

        if ($dto->type === 'dynamic' && in_array($dto->benefit_type, ['percentage', 'fixed_amount'])) {
            $this->calculateAction->execute($data);
        }
    }

    public function update(UpdateOfferDTO $dto)
    {
        $updated = [];
        if (! empty($dto->collectionData)) {
            $updated['collection'] = $this->updateCollection->execute($dto)['data'];
        }

        $collection = $updated['collection'] ?? $this->cms->getCollectionBySlug($dto->collectionSlug);
        if (! empty($dto->offerData)) {
            $updated['offer'] = $this->updateOffer->execute($collection['id'], $dto);
        }
        $offer = $updated['offer'] ?? $this->repository->findByCollectionId($collection['id']);

        $shouldRecalculate =
          (isset($dto->offerData['benefit_type']) || isset($dto->offerData['benefit_config']) || isset($dto->collectionData['conditions']))
          &&
          ($collection['type'] === 'dynamic' && in_array($offer['benefit_type'], ['percentage', 'fixed_amount']));

        if ($shouldRecalculate) {
            $data = [
                'collection' => $collection,
                'offer' => $offer,
            ];
            $this->deleteOfferPricesAction->execute($offer['id']);
            $entries = $this->calculateAction->execute($data);
            $this->reEvaluateAction->execute($entries);
        } elseif (in_array($offer['benefit_type'], ['quantity', 'total_price'])) {
            $this->deleteOfferPricesAction->execute($offer['id']);
        }

        return $updated;
    }

    public function show(string $collectionSlug)
    {
        return $this->showDetailsAction->execute($collectionSlug);
    }

    public function index(int $projectId)
    {
        return $this->indexAction->execute($projectId);
    }

    public function delete($collectionSlug)
    {
        $this->deleteOffer->execute($collectionSlug);
    }

    public function addItems(OfferItemsDTO $dto)
    {
        $data = $this->insertItemsAction->execute($dto);
        if ($data['message'] === 'Items added successfully' && $data['collection']['type'] === 'dynamic' && in_array($data['offer']['benefit_type'], ['percentage', 'fixed_amount'])) {
            $entries = $this->calculateAction->execute($data);
            $this->reEvaluateAction->execute($entries);
        }
    }

    public function removeItems(OfferItemsDTO $dto)
    {
        $this->removeItemsAction->execute($dto);
        $entries = array_map(fn ($id) => ['entry_id' => $id], $dto->items);
        $this->reEvaluateAction->execute($entries);
    }

    public function deactivate(ActivationOfferDTO $dto)
    {
        $this->deactivateOffer->execute($dto);
    }

    public function activate(ActivationOfferDTO $dto)
    {
        $this->activateOffer->execute($dto);
    }

    public function run(): array
    {
        return $this->action->execute();
    }

    public function subscribe(SubscribeDTO $dto)
    {
        $this->subscribeAction->execute($dto);
    }
}
