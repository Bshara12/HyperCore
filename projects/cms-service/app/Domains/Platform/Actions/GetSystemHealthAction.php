<?php

namespace App\Domains\Platform\Actions;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class GetSystemHealthAction
{
    /**
     * Probe every sibling service's /up endpoint plus this service's own
     * infrastructure.
     *
     * The HTTP probes run through Http::pool() so six ~3s responses cost one
     * round trip rather than eighteen seconds in series.
     *
     * @return array{
     *     services: list<array{key: string, label: string, status: string, http_status: int|null, latency_ms: int|null, error: string|null}>,
     *     dependencies: list<array{key: string, label: string, status: string, latency_ms: int|null, error: string|null}>,
     *     summary: array{total: int, up: int, down: int}
     * }
     */
    public function execute(): array
    {
        $services = $this->probeServices();

        $dependencies = $this->probeDependencies();

        $all = array_merge($services, $dependencies);

        $up = count(array_filter($all, fn ($c) => $c['status'] === 'up'));

        return [
            'services' => $services,
            'dependencies' => $dependencies,
            'summary' => [
                'total' => count($all),
                'up' => $up,
                'down' => count($all) - $up,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return list<array{key: string, label: string, status: string, http_status: int|null, latency_ms: int|null, error: string|null}>
     */
    private function probeServices(): array
    {
        /** @var list<array{key: string, label: string, url: string}> $configured */
        $configured = array_values(config('platform.services', []));

        if (empty($configured)) {
            return [];
        }

        $timeout = max(1, (int) config('platform.health_timeout', 5));

        $startedAt = microtime(true);

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($service) => $pool
                ->as($service['key'])
                ->timeout($timeout)
                ->get($service['url']),
            $configured
        ));

        // One shared elapsed figure: pooled requests overlap, so per-service
        // timings measured around the pool would all report the pool's total
        // and read as though every service were the slowest one.
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        $results = [];

        foreach ($configured as $service) {

            $response = $responses[$service['key']] ?? null;

            $results[] = $this->describeProbe(
                $service,
                $response,
                $elapsedMs
            );
        }

        return $results;
    }

    /**
     * @param  array{key: string, label: string, url: string}  $service
     * @return array{key: string, label: string, status: string, http_status: int|null, latency_ms: int|null, error: string|null}
     */
    private function describeProbe(
        array $service,
        mixed $response,
        int $elapsedMs
    ): array {

        $base = [
            'key' => $service['key'],
            'label' => $service['label'],
            'latency_ms' => $elapsedMs,
        ];

        // A pool entry is either a Response or the Throwable that replaced it
        // (connection refused, DNS failure, timeout).
        if ($response instanceof Throwable) {

            return $base + [
                'status' => 'unreachable',
                'http_status' => null,
                'error' => $this->shortMessage($response),
            ];
        }

        if ($response === null) {

            return $base + [
                'status' => 'unknown',
                'http_status' => null,
                'error' => 'No response recorded for this probe.',
            ];
        }

        $status = $response->status();

        return $base + [
            'status' => $response->successful() ? 'up' : 'down',
            'http_status' => $status,
            'error' => $response->successful()
                ? null
                : sprintf('Health endpoint returned HTTP %d.', $status),
        ];
    }

    /**
     * This service's own backing stores. A green service list with a red
     * database is the case worth surfacing, so they are reported side by side.
     *
     * @return list<array{key: string, label: string, status: string, latency_ms: int|null, error: string|null}>
     */
    private function probeDependencies(): array
    {
        return [
            $this->timed('database', 'CMS Database', function () {
                DB::connection()->getPdo();
                DB::select('SELECT 1');
            }),

            $this->timed('cache', 'Cache Store', function () {
                // Round-trips the configured store rather than asserting the
                // driver is merely constructible.
                cache()->put('platform:health:probe', 1, 10);
                cache()->get('platform:health:probe');
            }),

            $this->timed('redis', 'Redis', function () {
                Redis::connection()->ping();
            }),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, latency_ms: int|null, error: string|null}
     */
    private function timed(
        string $key,
        string $label,
        callable $probe
    ): array {

        $startedAt = microtime(true);

        try {
            $probe();

            return [
                'key' => $key,
                'label' => $label,
                'status' => 'up',
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => null,
            ];
        } catch (Throwable $e) {

            return [
                'key' => $key,
                'label' => $label,
                'status' => 'down',
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $this->shortMessage($e),
            ];
        }
    }

    private function shortMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '') {
            return $e::class;
        }

        // Driver exceptions carry multi-line dumps that would swamp the UI.
        $firstLine = strtok($message, "\n");

        return mb_strimwidth($firstLine ?: $message, 0, 200, '…');
    }
}
