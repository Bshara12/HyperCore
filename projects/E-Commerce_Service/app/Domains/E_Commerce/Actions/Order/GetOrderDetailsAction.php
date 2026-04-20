<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;

class GetOrderDetailsAction
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo
  ) {}

  public function execute(int $orderId, int $projectId, int $userId)
  {
    $order = $this->orderRepo->findDetailedForUser($orderId, $projectId, $userId);

    if (!$order) {
      throw new \Exception('Order not found');
    }

    return $order;
  }
}
