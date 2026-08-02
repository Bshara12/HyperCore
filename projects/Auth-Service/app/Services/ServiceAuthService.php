<?php

namespace App\Services;

use App\Events\SystemLogEvent;
use App\Models\ServiceClient;
use App\Repositories\ServiceClientRepositoryInterface;
use App\Repositories\SessionRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ServiceAuthService
{
    protected $jwt;
    protected $sessions;
    protected $services;

    public function __construct(
        JwtService $jwtService,
        SessionRepositoryInterface $sessionRepository,
        ServiceClientRepositoryInterface $serviceClientRepository
    ) {
        $this->jwt = $jwtService;
        $this->sessions = $sessionRepository;
        $this->services = $serviceClientRepository;
    }

    public function createService(string $name, string $clientSecret): ServiceClient
    {
        return $this->services->create([
            'name' => $name,
            'client_secret' => Hash::make($clientSecret),
            'client_id' => (string) Str::ulid(),
        ]);
    }

    public function issueToken(string $clientId, string $clientSecret): array
    {
        $client = $this->services->findByClientId($clientId);

        if (! $client) {
            return ['success' => false, 'message' => 'Invalid client'];
        }

        if (! Hash::check($clientSecret, $client->client_secret)) {
            return ['success' => false, 'message' => 'Invalid secret'];
        }

        $sessionId = $this->sessions->createServiceSession($client->client_id, $client->id);

        return [
            'success' => true,
            'access_token' => $this->jwt->generateServiceToken($client, $sessionId),
            'refresh_token' => $this->jwt->generateServiceRefreshToken($client, $sessionId),
        ];
    }

    public function refreshTokens(string $refreshToken): array
    {
        $decoded = $this->jwt->validateToken($refreshToken);

        if (! $decoded || $decoded->type !== 'refresh') {
            return ['success' => false, 'message' => 'Invalid refresh token'];
        }

        $record = $this->sessions->findValidServiceRefreshToken($decoded->jti);

        if (! $record || now()->gt($record->expires_at)) {
            return ['success' => false, 'message' => 'Refresh token expired'];
        }

        $client = $this->services->findById($decoded->sub);

        if (! $client) {
            return ['success' => false, 'message' => 'Service client not found'];
        }

        $this->sessions->revokeServiceRefreshToken($decoded->jti);

        return [
            'success' => true,
            'access_token' => $this->jwt->generateServiceToken($client, $record->session_id),
            'refresh_token' => $this->jwt->generateServiceRefreshToken($client, $record->session_id),
        ];
    }

    public function getServiceById(int $serviceId): ?ServiceClient
    {
        return $this->services->findByIdWithSessions($serviceId);
    }

    /**
     * ✅ جديد: حذف عميل خدمة نهائياً (hard delete)
     * يُستدعى حصراً من HyperCoreController بعد التحقق من isHyperCore
     */
    public function deleteService(int $serviceId, int $actingUserId): void
    {
        $service = $this->services->findById($serviceId);

        if (! $service) {
            throw new Exception('Service not found.');
        }

        $this->services->delete($service);

        event(new SystemLogEvent(
            module: 'auth',
            eventType: 'service_deleted',
            userId: $actingUserId,
            entityType: 'service_client',
            entityId: $serviceId,
        ));
    }
}
