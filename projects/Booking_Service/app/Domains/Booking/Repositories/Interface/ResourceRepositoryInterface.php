<?php

namespace App\Domains\Booking\Repositories\Interface;

use App\Domains\Booking\DTOs\ResourceDTO;
use App\Models\Resource;

interface ResourceRepositoryInterface
{
  // ─── Resource ─────────────────────────────────────────────────────────────
  public function create(ResourceDTO $dto): Resource;
  public function findById(int $id): ?Resource;
  public function update(Resource $resource, ResourceDTO $dto): Resource;
  public function delete(Resource $resource): void;
  public function listByProject(int $projectId): \Illuminate\Database\Eloquent\Collection;

  // ─── Availability ─────────────────────────────────────────────────────────
  public function setAvailabilities(Resource $resource, array $dtos): void;

  // ─── Cancellation Policy ──────────────────────────────────────────────────
  public function setPolicies(Resource $resource, array $dtos): void;}
