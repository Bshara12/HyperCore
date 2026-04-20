<?php

namespace App\Domains\Auth\Repository\Elequment;

use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ProjectUserRepository implements ProjectUserRepositoryInterface
{
    public function exists(int $userId, string $projectKey): bool
    {
        return DB::table('project_user')
            ->join('projects', 'projects.id', '=', 'project_user.project_id')
            ->where('project_user.user_id', $userId)
            ->where('projects.public_id', $projectKey)
            ->exists();
    }
}