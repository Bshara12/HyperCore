<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Actions\Order\AdminListOrdersAction;
use App\Domains\E_Commerce\Actions\Order\CreateOrderFromCartAction;
use App\Domains\E_Commerce\Actions\Order\EnrichOrderItemsAction;
use App\Domains\E_Commerce\Actions\Order\GetOrderDetailsAction;
use App\Domains\E_Commerce\Actions\Order\ListOrdersAction;
use App\Domains\E_Commerce\Actions\Order\UpdateOrderStatusAction;
use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\DTOs\Order\UpdateOrderStatusDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;

class OrderService
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo,
    protected CreateOrderFromCartAction $createFromCartAction,
    protected ListOrdersAction $listOrdersAction,
    protected EnrichOrderItemsAction $enrichOrderItemsAction,
    protected AdminListOrdersAction $adminListOrdersAction,
    protected GetOrderDetailsAction $getOrderDetailsAction,
    protected UpdateOrderStatusAction $updateOrderStatusAction
  ) {}

  public function createFromCart(CreateOrderDTO $dto)
  {
    return $this->createFromCartAction->execute($dto);
  }

  // للحذف
  public function getOrder(int $orderId, int $projectId, int $userId)
  {
    return $this->orderRepo->findByIdForUser($orderId, $projectId, $userId);
  }

  // public function listOrders(int $projectId, int $userId)
  // {
  //   return $this->listOrdersAction->execute($projectId, $userId);
  // }

  public function listOrders(int $projectId, int $userId)
  {
    $orders = $this->listOrdersAction->execute($projectId, $userId);

    return $this->enrichOrderItemsAction->execute($orders);
  }

  public function adminListOrders(int $projectId, array $filters = [])
  {
    $orders = $this->adminListOrdersAction->execute($projectId, $filters);

    return $this->enrichOrderItemsAction->execute($orders);
  }

  public function getOrderDetails(int $orderId, int $projectId, int $userId)
  {
    $order = $this->getOrderDetailsAction->execute($orderId, $projectId, $userId);

    return $this->enrichOrderItemsAction->execute(collect([$order]))->first();
  }

  public function updateOrderStatus(int $orderId, int $projectId, string $status)
  {
    return $this->updateOrderStatusAction->execute(
      new UpdateOrderStatusDTO($orderId, $projectId, $status)
    );
  }
}
