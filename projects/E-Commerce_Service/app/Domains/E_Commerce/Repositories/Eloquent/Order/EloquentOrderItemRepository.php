<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Order;

use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Models\OrderItem;

class EloquentOrderItemRepository implements OrderItemRepositoryInterface
{
    public function create(array $data)
    {
        return OrderItem::create($data);
    }

    public function update($item, array $data)
    {
        $item->update($data);

        return $item;
    }

    public function findByOrderAndItem(int $orderId, int $itemId)
    {
        return OrderItem::where('order_id', $orderId)
            ->where('product_id', $itemId) // ✅ صح
            ->first();
    }

    public function updateStatus(int $id, string $status)
    {
        return OrderItem::where('id', $id)
            ->update(['status' => $status]);
    }

    public function findById(int $id)
    {
        return OrderItem::find($id);
    }

    public function findByOrderId(int $orderId)
    {
        return OrderItem::where('order_id', $orderId)->get();
    }
}
