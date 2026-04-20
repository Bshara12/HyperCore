<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Order;

interface OrderItemRepositoryInterface
{
  public function create(array $data);
  public function update($item, array $data);
  public function findByOrderAndItem(int $orderId, int $itemId);

  public function findById(int $id);
  public function updateStatus(int $id, string $status);
  public function findByOrderId(int $orderId);
}
