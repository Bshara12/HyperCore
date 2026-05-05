<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;

class DeleteResourceAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'resource.delete';
    }

    public function __construct(
        private readonly ResourceRepositoryInterface $repository,
    ) {}

    public function execute(Resource $resource)
    {
        $this->run(function () use ($resource) {
            $this->repository->delete($resource);
            Cache::forget(CacheKeys::resource($resource->id));
            Cache::forget(CacheKeys::resources($resource->project_id));

            Cache::tags(["resource_{$resource->id}_bookings"])->flush();
        });
    }
}
