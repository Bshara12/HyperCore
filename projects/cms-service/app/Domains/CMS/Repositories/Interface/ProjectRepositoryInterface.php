<?php

namespace App\Domains\CMS\Repositories\Interface;

use App\Models\Project;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    public function create(array $data): Project;

    public function update(Project $project, array $data): Project;

    public function find(Project $project): Project;

    public function findByKey(string $key): Project;

    public function all(): Collection;

    public function delete(Project $project): void;

    // ⭐ جديد
    public function findById(int $id): Project;

    public function updateRatingStats(int $id, array $data): void;

    public function getRatingStats(int $id): array;
}
