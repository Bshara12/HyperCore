<?php

namespace App\Domains\Booking\Repositories\Interface;

use App\Domains\Booking\DTOs\ResourceDTO;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Collection;

interface ResourceRepositoryInterface
{
    // ─── Resource ─────────────────────────────────────────────────────────────
    public function create(ResourceDTO $dto): Resource;

    public function findById(int $id): ?Resource;

    public function update(Resource $resource, ResourceDTO $dto): Resource;

    public function delete(Resource $resource): void;

    public function listForUser(int $projectId, int $userId): Collection;

    public function listByProject(int $projectId): Collection;

    // ─── Availability ─────────────────────────────────────────────────────────
    public function setAvailabilities(Resource $resource, array $dtos): void;

    // ─── Cancellation Policy ──────────────────────────────────────────────────
    public function setPolicies(Resource $resource, array $dtos): void;
}
