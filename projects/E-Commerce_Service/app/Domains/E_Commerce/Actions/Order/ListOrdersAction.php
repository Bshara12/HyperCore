<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;

class ListOrdersAction
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo
  ) {}

  public function execute(int $projectId, int $userId)
  {
    $orders = $this->orderRepo->getUserOrders($projectId, $userId);

    return $orders;
  }
}