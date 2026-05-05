<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class GetResourceBookingsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'resource.getBookings';
    }

    public function __construct(
        private readonly BookingRepositoryInterface $repository,
    ) {}

    public function execute(GetResourceBookingsDTO $dto)
    {
        return $this->run(function () use ($dto) {
            // ✅ نعمل hash للـ filters لأنها ديناميكية
            $filtersHash = md5(json_encode([
                'status' => $dto->status,
                'from' => $dto->from,
                'to' => $dto->to,
            ]));

            // ✅ TTL_SHORT لأن الحجوزات تتغير باستمرار
            return Cache::tags(["resource_{$dto->resourceId}_bookings"])->remember(
                CacheKeys::resourceBookings($dto->resourceId, $filtersHash),
                CacheKeys::TTL_SHORT,
                fn () => $this->repository->listByResource(
                    resourceId: $dto->resourceId,
                    status: $dto->status,
                    from: $dto->from,
                    to: $dto->to
                )
            );
        });
    }
}
