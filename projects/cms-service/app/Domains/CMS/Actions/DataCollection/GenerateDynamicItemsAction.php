<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Services\DynamicCollectionQueryBuilder;
use App\Domains\CMS\Support\CollectionCache;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Log;

/**
 * Materialises a dynamic collection: runs its conditions and stores the
 * matching entries as collection items.
 *
 * This was the only action in the feature that did not extend Action, so it ran
 * with no transaction, no retry and no circuit breaker, inserting one row per
 * matched entry. A loose condition matching the whole project meant tens of
 * thousands of individual INSERTs, and a failure halfway through left the
 * collection permanently half-generated.
 */
class GenerateDynamicItemsAction extends Action
{
    /**
     * Upper bound on how many entries a single dynamic collection materialises.
     *
     * Conditions are author-supplied and can be arbitrarily loose (contains ""
     * matches everything), so this is the difference between a bounded write and
     * an unbounded one. Truncation is logged rather than silent.
     */
    public const MAX_ITEMS = 1000;

    protected function circuitServiceName(): string
    {
        return 'dataCollection.generateDynamicItems';
    }

    public function __construct(
        protected DataCollectionRepositoryInterface $repository,
        protected DynamicCollectionQueryBuilder $builder
    ) {}

    public function execute($collection)
    {
        return $this->run(function () use ($collection) {

            $entries = collect($this->builder->build($collection));

            if ($entries->count() > self::MAX_ITEMS) {
                Log::warning('[DataCollection] dynamic collection truncated', [
                    'collection_id' => $collection->id,
                    'project_id' => $collection->project_id,
                    'matched' => $entries->count(),
                    'kept' => self::MAX_ITEMS,
                ]);

                $entries = $entries->take(self::MAX_ITEMS);
            }

            // Replace rather than append: regenerating must be idempotent, both
            // because an update re-runs it and because Action::run retries.
            $this->repository->replaceItems(
                $collection->id,
                $entries->pluck('id')->all()
            );

            CollectionCache::forgetContents(
                $collection->project_id,
                $collection->slug,
                $collection->id
            );

            return $entries;
        });
    }
}
