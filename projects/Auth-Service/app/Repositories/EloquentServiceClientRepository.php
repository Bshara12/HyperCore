<?php

namespace App\Repositories;

use App\Models\ServiceClient;

class EloquentServiceClientRepository implements ServiceClientRepositoryInterface
{
    public function create(array $data): ServiceClient
    {
        return ServiceClient::create($data);
    }

    public function findById(int $id): ?ServiceClient
    {
        return ServiceClient::find($id);
    }

    public function findByIdWithSessions(int $id): ?ServiceClient
    {
        return ServiceClient::with('sessions')->find($id);
    }

    public function findByClientId(string $clientId): ?ServiceClient
    {
        return ServiceClient::where('client_id', $clientId)->first();
    }

    public function delete(ServiceClient $service): bool
    {
        return (bool) $service->delete();
    }
}
