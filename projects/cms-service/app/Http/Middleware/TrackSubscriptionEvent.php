<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Services\DomainEventService;

class TrackSubscriptionEvent
{
    public function __construct(
        private DomainEventService $domainEventService
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $eventKey
    ) {

        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Execute ONLY on successful requests
        |--------------------------------------------------------------------------
        */

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $userId = $request->user_id
            ?? $request->input('user_id');

        $projectId = $request->project_id
            ?? $request->input('project_id');

        if (!$userId) {
            return $response;
        }

        $this->domainEventService
            ->dispatch(

                userId: (int) $userId,

                projectId: $projectId
                    ? (int) $projectId
                    : null,

                eventKey: $eventKey
            );

        return $response;
    }
}