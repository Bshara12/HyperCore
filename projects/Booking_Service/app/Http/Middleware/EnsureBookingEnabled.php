<?php

namespace App\Http\Middleware;

use App\Services\CMS\CMSApiClient;
use Closure;

class EnsureBookingEnabled
{
    public function __construct(
        protected CMSApiClient $cms
    ) {}

    public function handle($request, Closure $next)
    {
        $project = $request->get('project');

        $modules = $project['enabled_modules'] ?? [];

        if (! in_array('booking', $modules)) {
            abort(403, 'Booking module is not enabled for this project');
        }

        return $next($request);
    }
}
