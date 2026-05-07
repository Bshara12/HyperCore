<?php

namespace App\Domains\Notifications\DTOs;

use Illuminate\Http\Request;

final class NotificationActor
{
    public function __construct(
        public readonly string $type,               // user | service
        public readonly string|int|null $id,
        public readonly array $permessions = [],
        public readonly ?string $projectId = null,
        public readonly array $raw = [],
        public readonly ?string $requestId = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $causationId = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $project = $request->attributes->get('project', []);
        $service = $request->attributes->get('auth_service_client');
        $user = $request->attributes->get('auth_user', []);

        $requestId = $request->header('X-Request-Id');
        $correlationId = $request->header('X-Correlation-Id');
        $causationId = $request->header('X-Causation-Id');

        if ($service) {
            return new self(
                type: 'service',
                id: $service->id,
                permessions: data_get($service, 'scopes', []),
                projectId: data_get($project, 'string'),
                raw: ['service' => $service],
                requestId: $requestId,
                correlationId: $correlationId,
                causationId: $causationId,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return new self(
            type: 'user',
            id: data_get($user, 'id'),
            permessions: data_get($user, 'permessions', []),
            projectId: data_get($project, 'string'),
            raw: $user,
            requestId: $requestId,
            correlationId: $correlationId,
            causationId: $causationId,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            id: $data['id'],
            permessions: $data['permessions'] ?? [],
            projectId: $data['project_id'] ?? null,
            raw: $data['raw'] ?? [],
            requestId: $data['request_id'] ?? null,
            correlationId: $data['correlation_id'] ?? null,
            causationId: $data['causation_id'] ?? null,
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
        );
    }

    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permessions, true);
    }

    public function snapshot(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'project_id' => $this->projectId,
            'permessions' => $this->permessions,
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'raw' => $this->raw,
        ];
    }
}
