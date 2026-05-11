<?php

namespace App\Domains\Subscription\DTOs\Rule;

class AuthorizeEventDTO
{
    public function __construct(

        public readonly int $userId,

        public readonly ?int $projectId,

        public readonly string $eventKey
    ) {}
}