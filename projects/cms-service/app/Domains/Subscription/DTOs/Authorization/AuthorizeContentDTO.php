<?php

namespace App\Domains\Subscription\DTOs\Authorization;

class AuthorizeContentDTO
{
    public function __construct(

        public readonly ?int $userId,

        public readonly ?int $projectId,

        public readonly string $contentType,

        public readonly int $contentId
    ) {}
}