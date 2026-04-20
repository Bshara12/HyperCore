<?php

namespace App\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;

class CreateReturnRequestAction
{
  public function __construct(
    protected ReturnRequestRepositoryInterface $repo,
    protected OrderItemRepositoryInterface $orderItemRepo
  ) {}

  public function execute(CreateReturnRequestDTO $dto)
  {
    $orderItem = $this->orderItemRepo->findById($dto->order_item_id);

    if ($orderItem->status !== 'delivered') {
      throw new \Exception('Only delivered items can be returned');
    }

    if ($this->repo->findPendingByItem($dto->order_item_id)) {
      throw new \Exception('Request already exists');
    }

    return $this->repo->create([
      'user_id' => $dto->user_id,
      'order_id' => $dto->order_id,
      'order_item_id' => $dto->order_item_id,
      'description' => $dto->description,
      'quantity' => $dto->quantity,
      'status' => 'pending',
      'project_id' => $dto->project_id
    ]);
  }
}