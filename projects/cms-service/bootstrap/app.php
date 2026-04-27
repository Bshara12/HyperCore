<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

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
      'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    //
    $exceptions->render(function (ModelNotFoundException $e) {
      if ($e->getModel() === Project::class) {
        return response()->json([
          'message' => 'Project not found'
        ], 404);
      }
    });
  })->withCommands([
    \App\Console\Commands\SearchReindexCommand::class,
  ])
  ->withSchedule(function (Schedule $schedule) {
    $schedule->command('app:publish-scheduled-entries')
      ->everyMinute();
  })->create();
