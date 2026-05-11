<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Authorization\AuthorizeEventAction;
use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;

class AuthorizationEngineService
{
    public function __construct(
        private AuthorizeEventAction $action
    ) {}

    public function authorize(
        AuthorizeEventDTO $dto
    ): void {

        $this->action
            ->execute($dto);
    }
}