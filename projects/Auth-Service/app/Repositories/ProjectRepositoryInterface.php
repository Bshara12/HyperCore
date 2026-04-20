<?php

namespace App\Repositories;

use App\Models\Invitation;
use App\Models\Project;

interface ProjectRepositoryInterface {
    public function create(array $data): Project;
    public function update(Project $project, array $data): bool;
    public function createInvitation(array $data): Invitation;
    public function updateInvitation(Invitation $invitation, array $data):bool;
}
