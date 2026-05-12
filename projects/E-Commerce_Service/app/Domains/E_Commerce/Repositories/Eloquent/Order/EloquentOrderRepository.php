<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function findByIdForUser(int $id, int $projectId, int $userId): ?Order
    {
        return Order::where('id', $id)
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();
    }

    public function loadItems(Order $order): Order
    {
        $order->load('items');

        return $order;
    }

    public function getUserOrders(int $projectId, int $userId)
    {
        return Order::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->with('items') // 🔥 مهم
            ->latest()
            ->get();
    }

    public function getAllOrders(int $projectId, array $filters = [])
    {
        $query = Order::query()
            ->where('project_id', $projectId)
            ->with('items');

        // 🔥 filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // 🔥 performance
        return $query
            ->latest()
            ->paginate(15);
    }

    // public function findDetailedForUser(int $id, int $projectId, int $userId): ?Order
    // {
    //   return Order::where('id', $id)
    //     ->where('project_id', $projectId)
    //     ->where('user_id', $userId)
    //     ->with('items')
    //     ->first();
    // }
    public function findDetailedForUser(int $id, int $projectId, int $userId): ?Order
    {
        return Order::where('id', $id)
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->select([
                'id',
                'user_id',
                'project_id',
                'status',
                'total_price',
                'address',
                'created_at',
            ]) // 🔥 هون مكانها
            ->with('items')
            ->first();
    }

    public function updateItemsStatus(int $orderId, string $status): void
    {
        DB::table('order_items')
            ->where('order_id', $orderId)
            ->update(['status' => $status]);
    }

    public function updateStatus(int $id, string $status)
    {
        return Order::where('id', $id)
            ->update(['status' => $status]);
    }
}
