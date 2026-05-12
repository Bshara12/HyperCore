<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;

use App\Domains\Subscription\Actions\Authorization\AuthorizeContentAccessAction;

class ContentAuthorizationService
{
    public function __construct(
        private AuthorizeContentAccessAction $action
    ) {}

    public function authorize(
        AuthorizeContentDTO $dto
    ): void {

        $this->action
            ->execute($dto);
    }
}