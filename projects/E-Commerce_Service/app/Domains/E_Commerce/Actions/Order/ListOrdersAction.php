<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class ListOrdersAction
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo
    ) {}

    public function execute(int $projectId, int $userId)
    {
        return Cache::remember(
            CacheKeys::userOrders($userId, $projectId),
            CacheKeys::TTL_MEDIUM,
            fn () => $this->orderRepo->getUserOrders($projectId, $userId)
        );
    }
}
