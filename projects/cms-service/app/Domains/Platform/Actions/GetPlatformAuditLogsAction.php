<?php

namespace App\Domains\Platform\Actions;

use App\Domains\Platform\Services\LoggingServiceClient;
use Throwable;

class GetPlatformAuditLogsAction
{
    public function __construct(
        private LoggingServiceClient $client
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        try {
            return [
                'available' => true,
                'result' => $this->client->auditLogs(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'available' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
