<?php

namespace App\Services;

use App\Models\ServiceClient;
use App\Repositories\SessionRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ServiceAuthService
{
    protected $jwt;
    protected $sessions;

    public function __construct(JwtService $jwtService, SessionRepositoryInterface $sessionRepository)
    {
        $this->jwt = $jwtService;
        $this->sessions = $sessionRepository;
    }

    public function createService(string $name, string $clientSecret): ServiceClient
    {
        return ServiceClient::create([
            'name' => $name,
            'client_secret' => Hash::make($clientSecret),
            'client_id' => (string) Str::ulid(),
        ]);
    }

    public function issueToken(string $clientId, string $clientSecret): array
    {
        $client = ServiceClient::where('client_id', $clientId)->first();

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

    /**
     * نظير AuthService::refreshTokens بس للخدمات — جدول service_refresh_tokens منفصل تماماً
     */
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

        $client = ServiceClient::find($decoded->sub);

        if (! $client) {
            return ['success' => false, 'message' => 'Service client not found'];
        }

        // Rotation: إبطال القديم قبل توليد الجديد لمنع إعادة الاستخدام
        $this->sessions->revokeServiceRefreshToken($decoded->jti);

        return [
            'success' => true,
            'access_token' => $this->jwt->generateServiceToken($client, $record->session_id),
            'refresh_token' => $this->jwt->generateServiceRefreshToken($client, $record->session_id),
        ];
    }

    public function getServiceById(int $serviceId): ?ServiceClient
    {
        return ServiceClient::with('sessions')->find($serviceId);
    }
}
