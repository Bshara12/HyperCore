<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
      'resolve.project' => \App\Http\Middleware\ResolveProject::class,
      'auth.user' => \App\Http\Middleware\AuthUserMiddleware::class,
      'ecommerce.enabled' => \App\Http\Middleware\EnsureEcommerceEnabled::class,
      'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    //
  })->withSchedule(function (Schedule $schedule) {
    $schedule->command('offers:process-schedule')
      ->everyMinute();
  })->create();
