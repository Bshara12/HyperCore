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
    $cbMock->shouldReceive('reportFailure')->andReturn(true)->byDefault();
    
    // تسجيل الـ Mock في الحاوية ليتم استخدامه بدلاً من الخدمة الحقيقية
    app()->instance(CircuitBreakerService::class, $cbMock);
});

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