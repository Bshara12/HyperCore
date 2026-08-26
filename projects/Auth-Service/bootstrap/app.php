<?php

use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\ServiceAuthMiddleware;
use App\Http\Middleware\VerifyInternalApiKey;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => JwtMiddleware::class,
            'service.auth' => ServiceAuthMiddleware::class,
            'internal.api' => VerifyInternalApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ✅ يضمن إن أي خطأ بمسارات api/* يرجع JSON دايماً، بغض النظر عن Accept header
        // (مهم لأنه المستهلكين هني خدمات تانية، مش متصفح)
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found'], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found'], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // ✅ شبكة أمان أخيرة: أي استثناء غير متوقع، فقط لما APP_DEBUG=false
        // (بالتطوير المحلي بيضل يظهر تفاصيل الخطأ الكاملة عادةً، مفيد للتصحيح)
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') && ! config('app.debug')) {
                return response()->json(['message' => 'Server error, please try again later.'], 500);
            }
        });
    })->create();
