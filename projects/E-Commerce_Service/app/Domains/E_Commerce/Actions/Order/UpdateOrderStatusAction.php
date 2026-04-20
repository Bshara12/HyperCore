<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\DTOs\Order\UpdateOrderStatusDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusAction
{
  public function __construct(
    protected OrderRepositoryInterface $orderRepo
  ) {}

  // public function execute(UpdateOrderStatusDTO $dto)
  // {
  //   return DB::transaction(function () use ($dto) {

  //     $order = $this->orderRepo->findById($dto->order_id);

  //     if (!$order || $order->project_id !== $dto->project_id) {
  //       throw new \Exception('Order not found');
  //     }

  //     // 🔥 State Machine Validation
  //     $this->validateTransition($order->status, $dto->status);

  //     // 🔥 update
  //     $order->update([
  //       'status' => $dto->status
  //     ]);

  //     return $order;
  //   });
  // }

  public function execute(UpdateOrderStatusDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      $order = $this->orderRepo->findById($dto->order_id);

      if (!$order || $order->project_id !== $dto->project_id) {
        throw new \Exception('Order not found');
      }

      $this->validateTransition($order->status, $dto->status);

      // 🔥 خزّن الحالة القديمة
      $oldStatus = $order->status;

      // 🔥 تحديث order
      $order->update([
        'status' => $dto->status
      ]);

      // 🔥 تحديث كل items (هون المطلوب)
      $this->orderRepo->updateItemsStatus($order->id, $dto->status);

      return $order;
    });
  }
  private function validateTransition(string $current, string $new)
  {
    $allowed = [
      'pending' => ['paid', 'cancelled'],
      'paid' => ['shipped', 'cancelled'],
      'shipped' => ['delivered'],
      'delivered' => [],
      'cancelled' => [],
    ];

    if (!in_array($new, $allowed[$current] ?? [])) {
      throw new \Exception("Invalid status transition from $current to $new");
    }
  }
}
