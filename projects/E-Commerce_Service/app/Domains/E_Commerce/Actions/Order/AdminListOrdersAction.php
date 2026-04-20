<?php
namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;

class AdminListOrdersAction
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo
  ) {}

  public function execute(int $projectId, array $filters = [])
  {
    return $this->orderRepo->getAllOrders($projectId, $filters);
  }
}