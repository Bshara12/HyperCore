<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\Auth\Service\AuthServiceClient;
use App\Models\Project;

class LeaveProjectAction
{
    public function __construct(
        private AuthServiceClient $authClient
    ) {}

    public function execute(Project $project, int $userId): array
    {
        return $this->authClient->leaveProject($project->id, $userId);
    }
}
