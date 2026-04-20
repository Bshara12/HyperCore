<?php

namespace App\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UpdateReturnRequestAction
{
  public function __construct(
    protected ReturnRequestRepositoryInterface $repo,
    protected OrderItemRepositoryInterface $orderItemRepo,
    private OrderRepositoryInterface $orderRepo,
  ) {}

  // public function execute(UpdateReturnRequestDTO $dto)
  // {
  //   return DB::transaction(function () use ($dto) {

  //     $request = $this->repo->findById($dto->id);

  //     if (!$request || $request->status !== 'pending') {
  //       throw new \Exception('Invalid request');
  //     }

  //     $this->repo->update($request, [
  //       'status' => $dto->status
  //     ]);

  //     if ($dto->status === 'approved') {
  //       $this->orderItemRepo->updateStatus(
  //         $request->order_item_id,
  //         'returned'
  //       );
  //     }

  //     return $request;
  //   });
  // }

  public function execute(UpdateReturnRequestDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      $request = $this->repo->findById($dto->id);

      if (!$request || $request->status !== 'pending') {
        throw new \Exception('Invalid request');
      }

      // 🔥 تحديث الطلب
      $this->repo->update($request, [
        'status' => $dto->status
      ]);

      // if ($dto->status === 'approved') {

      //   // ✅ 1. تحديث order_item
      //   $this->orderItemRepo->updateStatus(
      //     $request->order_item_id,
      //     'returned'
      //   );

      //   // ✅ 2. جلب كل عناصر الطلب
      //   $items = $this->orderItemRepo->findByOrderId($request->order_id);

      //   $totalItems = count($items);
      //   $returnedItems = collect($items)
      //     ->where('status', 'returned')
      //     ->count();

      //   // ✅ 3. تحديد حالة الطلب
      //   if ($returnedItems === $totalItems) {
      //     $newStatus = 'returned';
      //   } else {
      //     $newStatus = 'partially_returned';
      //   }

      //   // ✅ 4. تحديث order
      //   $this->orderItemRepo->updateStatus(
      //     $request->order_id,
      //     $newStatus
      //   );
      // }


      if ($dto->status === 'approved') {

        // ✅ 1. حدث item واحد فقط
        $this->orderItemRepo->updateStatus(
          $request->order_item_id,
          'returned'
        );

        // ✅ 2. جيب كل عناصر الطلب
        $items = $this->orderItemRepo->findByOrderId($request->order_id);

        $totalItems = $items->count();
        $returnedItems = $items->where('status', 'returned')->count();

        // ✅ 3. حدد حالة الطلب
        if ($returnedItems === $totalItems) {
          $newStatus = 'returned';
        } else {
          $newStatus = 'partially_returned';
        }

        // ✅ 4. حدث order فقط (مو items!)
        $this->orderRepo->updateStatus(
          $request->order_id,
          $newStatus
        );
      }
      return $request;
    });
  }
}
