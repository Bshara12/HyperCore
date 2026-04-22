<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\CMS\CMSApiClient;

class EnsureEcommerceEnabled
{
  public function __construct(
    protected CMSApiClient $cms
  ) {}

  // public function handle($request, Closure $next)
  // {
  //     // 🔥 المشروع تم حله مسبقاً من ResolveProject
  //     $project = $this->cms->resolveProject();

  //     $modules = $project['enabled_modules'] ?? [];

  //     // 🔴 إذا ecommerce مو مفعّل
  //     if (!in_array('ecommerce', $modules)) {
  //         abort(403, 'Ecommerce module is not enabled for this project');
  //     }

  //     return $next($request);
  // }
  public function handle($request, Closure $next)
  {
    $project = $request->get('project');

    $modules = $project['enabled_modules'] ?? [];

    if (!in_array('booking', $modules)) {
      abort(403, 'Booking module is not enabled for this project');
    }

    return $next($request);
  }
}
