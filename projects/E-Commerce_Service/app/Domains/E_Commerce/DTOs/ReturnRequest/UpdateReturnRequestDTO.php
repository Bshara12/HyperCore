<?php

namespace App\Domains\E_Commerce\DTOs\ReturnRequest;

class UpdateReturnRequestDTO
{
    public function __construct(
        public int $id,
        public string $status,
    ) {}
}
