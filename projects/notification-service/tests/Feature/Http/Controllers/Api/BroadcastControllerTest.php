<?php
namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\Api\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastControllerTest extends TestCase
{
    #[Test]
    public function it_successfully_authenticates_broadcasting_request_for_valid_user()
    {
        // استخدام Mock للـ Broadcast facade للتحقق من أن النظام يقوم بالمصادقة بشكل صحيح
        Broadcast::shouldReceive('auth')
            ->once()
            ->withArgs(function ($request) {
                // التأكد من أن المستخدم المؤقت تم تعيينه بنجاح على الـ Request ولديه الـ ID الصحيح
                return $request->user() !== null 
                    && $request->user()->getAuthIdentifier() === '123';
            })
            ->andReturn(['auth' => 'test_signature_hash']);

        // تجهيز طلب HTTP وهمي مع تمرير بيانات المستخدم في الـ attributes (محاكاة للـ Middleware)
        $request = Request::create('/api/broadcasting/auth', 'POST', [
            'channel_name' => 'private-user.123',
        ]);
        
        $request->attributes->set('authenticated_user', ['id' => '123']);

        // استدعاء الكونترولر مباشرة
        $controller = new BroadcastController();
        $response = $controller->authenticate($request);

        // التحقق من أن النتيجة هي النتيجة المتوقعة من عملية المصادقة
        $this->assertEquals(['auth' => 'test_signature_hash'], $response);
    }
}