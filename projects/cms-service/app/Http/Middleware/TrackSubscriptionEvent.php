<?php

namespace App\Http\Middleware;

use App\Domains\Subscription\Services\DomainEventService;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;

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

        /*
        |--------------------------------------------------------------------------
        | The identity always comes from the authenticated user set by
        | AuthUserMiddleware — never from request input, which a client can
        | forge to burn (or bypass) somebody else's quota.
        |--------------------------------------------------------------------------
        */

        $user = $request->attributes->get('auth_user');

        $userId = $user['id'] ?? null;

        if (! $userId) {
            return $response;
        }

        $project = CurrentProject::resolve($request);

        $this->domainEventService
            ->dispatch(

                userId: (int) $userId,

                projectId: $project?->id,

                eventKey: $eventKey
            );

        return $response;
    }
}
