<?php

namespace App\Domains\Auth\DTOs;


class CheckProjectAccessDto
{
    public function __construct(
        public int $userId,
        public string $projectKey
    ) {}
}