<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\Auth\Service\AuthServiceClient;
use App\Models\Project;

class ListProjectMembersAction
{
    public function __construct(
        private AuthServiceClient $authClient
    ) {}

    public function execute(Project $project): array
    {
        return $this->authClient->getProjectMembers($project->id);
    }
}
