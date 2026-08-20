<?php

namespace App\Domains\CMS\DTOs\Project;

use App\Domains\CMS\Requests\JoinProjectRequest;
use App\Models\Project;

readonly class JoinProjectDTO
{
    public function __construct(
        public int $projectId,
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(JoinProjectRequest $request, Project $project): self
    {
        return new self(
            projectId: $project->id,
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
        );
    }
}
