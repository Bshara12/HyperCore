<?php

namespace App\Domains\Platform\Actions;

use App\Domains\Platform\Services\LoggingServiceClient;
use Throwable;

class GetPlatformLogsAction
{
    public function __construct(
        private LoggingServiceClient $client
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters): array
    {
        try {
            return [
                'available' => true,
                'result' => $this->client->logs($filters),
                'error' => null,
            ];
        } catch (Throwable $e) {
            // The Logging Service is a separate deployment; when it is down the
            // operator needs to SEE that, not get a 500 for the whole page.
            report($e);

            return [
                'available' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
