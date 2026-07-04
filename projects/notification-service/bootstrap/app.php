<?php

use App\Http\Middleware\ServiceAuthMiddleware;
use App\Http\Middleware\UserAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api:      __DIR__ . '/../routes/api.php',
        channels: __DIR__ . '/../routes/channels.php',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // تسجيل الـ Aliases للـ Middleware المخصصة
        $middleware->alias([
            'auth.service' => ServiceAuthMiddleware::class,
            'auth.user'    => UserAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // معالجة أخطاء الـ Validation بصيغة موحدة
        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            \Illuminate\Http\Request $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        });

        // معالجة عدم وجود الـ Resource
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });

        // معالجة أخطاء التواصل مع الخدمات الأخرى
        $exceptions->render(function (
            \Illuminate\Http\Client\ConnectionException $e,
            \Illuminate\Http\Request $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Service unavailable. Please try again later.',
            ], 503);
        });

        // معالجة حالة عدم وجود المستخدم في Auth Service
        $exceptions->render(function (
            \App\Exceptions\UserNotFoundException $e,
            \Illuminate\Http\Request $request
        ) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        });
    })
    ->create();
