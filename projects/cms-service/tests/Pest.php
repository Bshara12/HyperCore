<?php

use Illuminate\Support\Facades\Queue;
use App\Domains\Core\Services\CircuitBreakerService;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// اجعل جميع اختبارات Feature و Unit تستخدم TestCase الأساسي
uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Global Setup
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    // 1. حل مشكلة AMQPStreamConnection (RabbitMQ)
    // Queue::fake() تمنع Laravel من محاولة الاتصال بـ RabbitMQ فعلياً
    Queue::fake();

    // 2. حل مشكلة CircuitBreakerService
    // نقوم بعمل Mock افتراضي (byDefault) لكي لا يشتكي الاختبار إذا تم استدعاؤه دون توقعات
    $cbMock = Mockery::mock(CircuitBreakerService::class);
    $cbMock->shouldReceive('canProceed')->andReturn(true)->byDefault();
    $cbMock->shouldReceive('reportSuccess')->andReturn(true)->byDefault();
    $cbMock->shouldReceive('reportFailure')->andReturn(true)->byDefault();
    $cbMock->shouldIgnoreMissing();

    // تسجيل الـ Mock في الحاوية ليتم استخدامه بدلاً من الخدمة الحقيقية
    app()->instance(CircuitBreakerService::class, $cbMock);
});

/*
|--------------------------------------------------------------------------
| Custom Functions
|--------------------------------------------------------------------------
*/

/**
 * Bind a project as the resolved tenant, the way the ResolveProject middleware
 * does at runtime. Anything reading CurrentProject::id() needs this.
 */
function bindCurrentProject(?\App\Models\Project $project = null): \App\Models\Project
{
    $project ??= \App\Models\Project::factory()->create();

    app()->instance('currentProject', $project);

    return $project;
}

/**
 * Build a FormRequest that believes it was dispatched on a route carrying the
 * given parameters, so rules() depending on $this->route(...) can run.
 *
 * @template T of \Illuminate\Foundation\Http\FormRequest
 *
 * @param  class-string<T>  $requestClass
 * @return T
 */
function requestWithRouteParams(string $requestClass, array $parameters)
{
    $request = new $requestClass;

    $request->setRouteResolver(fn () => new class($parameters)
    {
        public function __construct(private array $parameters) {}

        public function parameter($name, $default = null)
        {
            return $this->parameters[$name] ?? $default;
        }
    });

    return $request;
}

/*
|--------------------------------------------------------------------------
| Custom Functions
|--------------------------------------------------------------------------
*/

function validateRequest(array $data, $requestClass)
{
    $request = new $requestClass();
    return \Illuminate\Support\Facades\Validator::make($data, $request->rules());
}