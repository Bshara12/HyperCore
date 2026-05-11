<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Order;

use App\Models\Order;

interface OrderRepositoryInterface
{
    public function create(array $data): Order;

    public function findById(int $id): ?Order;

    public function findByIdForUser(int $id, int $projectId, int $userId): ?Order;

    public function loadItems(Order $order): Order;

    public function getUserOrders(int $projectId, int $userId);

    public function getAllOrders(int $projectId, array $filters = []);

    public function findDetailedForUser(int $id, int $projectId, int $userId): ?Order;

    public function updateItemsStatus(int $orderId, string $status): void;

    public function updateStatus(int $id, string $status);
}
