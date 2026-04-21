<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class AdminListOrdersAction
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo
  ) {}

  public function execute(int $projectId, array $filters = [])
  {
    $filtersHash = md5(json_encode($filters));

    return Cache::tags(['admin_orders'])->remember(
      CacheKeys::adminOrders($projectId, $filtersHash),
      CacheKeys::TTL_SHORT,
      fn() => $this->orderRepo->getAllOrders($projectId, $filters)
    );
  }
}
