<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetOrderDetailsAction
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo
    ) {}

    public function execute(int $orderId, int $projectId, int $userId)
    {
        return Cache::remember(
            CacheKeys::order($orderId, $userId),
            CacheKeys::TTL_MEDIUM,
            function () use ($orderId, $projectId, $userId) {

                $order = $this->orderRepo->findDetailedForUser($orderId, $projectId, $userId);

                if (! $order) {
                    throw new \Exception('Order not found');
                }

                return $order;
            }
        );
    }
}
