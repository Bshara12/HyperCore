<?php

use App\Console\Commands\SearchReindexCommand;
use App\Exceptions\ContentEntryNotFoundException;
use App\Exceptions\SubscriptionException;
use App\Http\Middleware\AuthUserMiddleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ResolveProject;
use App\Http\Middleware\TrackSubscriptionEvent;
use App\Models\Project;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'resolve.project' => ResolveProject::class,
            'auth.user' => AuthUserMiddleware::class,
            'permission' => CheckPermission::class,
            'track.event' => TrackSubscriptionEvent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
        $exceptions->render(function (ModelNotFoundException $e) {
            if ($e->getModel() === Project::class) {
                return response()->json([
                    'message' => 'Project not found',
                ], 404);
            }
        });
        $exceptions->render(
            function (
                SubscriptionException $e,
                Request $request
            ) {

                return response()->json([

                    'message' => $e->getMessage(),

                    'context' => $e->context(),

                ], 403);
            }
        );

        $exceptions->render(
            function (
                ContentEntryNotFoundException $e,
                Request $request
            ) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
        );
    })->withCommands([
      SearchReindexCommand::class,
  ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('app:publish-scheduled-entries')
            ->everyMinute();
    })->create();
