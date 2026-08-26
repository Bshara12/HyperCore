<?php

namespace App\Domains\Platform\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the Logging Service on behalf of the operator dashboard.
 *
 * The dashboard does NOT call the Logging Service directly: its /api/logs and
 * /api/audit-logs routes carry no authentication at all (only a throttle), so
 * putting them in front of a browser would publish every tenant's audit trail
 * to anyone who knows the port. Proxying here keeps that data behind the
 * `hypercore` gate on the platform routes.
 */
class LoggingServiceClient
{
    private function baseUrl(): string
    {
        $url = config('services.logging_service.url');

        if (! $url) {
            throw new RuntimeException(
                'LOGGING_URL is not configured; cannot read platform logs.'
            );
        }

        return rtrim($url, '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.logging_service.timeout', 10));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function logs(array $params): array
    {
        return $this->get('/logs', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditLogs(): array
    {
        return $this->get('/audit-logs', []);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params): array
    {
        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->get($this->baseUrl().$path, array_filter(
                $params,
                fn ($value) => $value !== null && $value !== ''
            ));

        if (! $response->successful()) {

            throw new RuntimeException(sprintf(
                'Logging Service returned HTTP %d.',
                $response->status()
            ));
        }

        $body = $response->json();

        // /logs answers with a paginator object; /audit-logs answers with a
        // bare array. Normalise so callers get an array either way.
        return is_array($body)
            ? $body
            : ['data' => []];
    }
}
