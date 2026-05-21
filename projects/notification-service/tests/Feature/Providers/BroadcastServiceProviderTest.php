<?php

namespace Tests\Feature\Providers;

use Tests\TestCase;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class BroadcastServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_broadcasting_routes_with_correct_middlewares()
    {
        // 1. تسجيل الـ Provider رسمياً داخل حاوية التطبيق الحالية للتست
        $provider = $this->app->register(BroadcastServiceProvider::class);
        
        // 2. إجبار الحاوية على تشغيل ميثود الـ boot في سياق التطبيق النشط
        $provider->boot();

        // 3. الفحص الفعلي: البحث عن المسار بالـ URL المباشر (broadcasting/auth) لضمان تسجيله
        $route = collect(Route::getRoutes())->first(function ($route) {
            return $route->uri() === 'broadcasting/auth';
        });

        // التأكد من أن المسار تم العثور عليه بنجاح
        $this->assertNotNull($route, 'Broadcasting auth route (broadcasting/auth) was not registered.');

        // 4. التحقق من أن الـ Middlewares المحددة في الـ Provider تم تطبيقها بدقة
        $middlewares = $route->gatherMiddleware();

        $this->assertContains('auth.user', $middlewares, 'Route is missing "auth.user" middleware.');
        $this->assertContains('resolve.project', $middlewares, 'Route is missing "resolve.project" middleware.');
    }
}