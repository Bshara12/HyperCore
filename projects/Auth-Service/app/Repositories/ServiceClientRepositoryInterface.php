<?php

namespace App\Repositories;

use App\Models\ServiceClient;

interface ServiceClientRepositoryInterface
{
    public function create(array $data): ServiceClient;

    public function findById(int $id): ?ServiceClient;

    public function findByIdWithSessions(int $id): ?ServiceClient;

    public function findByClientId(string $clientId): ?ServiceClient;

    public function delete(ServiceClient $service): bool;
}
