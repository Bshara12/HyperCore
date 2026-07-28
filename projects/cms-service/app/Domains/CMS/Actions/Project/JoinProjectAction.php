<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\Auth\Service\AuthServiceClient;
use App\Domains\CMS\DTOs\Project\JoinProjectDTO;

class JoinProjectAction
{
    public function __construct(
        private AuthServiceClient $authClient
    ) {}

    public function execute(JoinProjectDTO $dto): array
    {
        return $this->authClient->joinProject($dto->projectId, [
            'name'     => $dto->name,
            'email'    => $dto->email,
            'password' => $dto->password,
        ]);
    }
}
