<?php

namespace App\Domains\E_Commerce\DTOs\Order;

class UpdateOrderStatusDTO
{
    public function __construct(
        public int $order_id,
        public int $project_id,
        public string $status
    ) {}

    public static function fromRequest($request, int $orderId): self
    {
        return new self(
            order_id: $orderId,
            project_id: $request->project_id,
            status: $request->status
        );
    }
}
