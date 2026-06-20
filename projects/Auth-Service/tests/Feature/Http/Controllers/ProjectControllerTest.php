<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Http;

// 1. اختبار المسار الناجح عندما يمتلك المستخدم صلاحية الوصول
test('exsists_in_project returns true when microservice confirms access', function () {
// محاكاة رد السيرفيس الخارجي بإرجاع true
Http::fake([
'http://localhost:8001/api/check-project-access' => Http::response(['has_access' => true], 200),
]);

// استدعاء الكنترولر من الـ Container لتمرير الـ dependencies تلقائياً
$controller = app(ProjectController::class);

// تشغيل الدالة مباشرة
$result = $controller->exsists_in_project(userId: 5, projectId: 12);

// التأكيد على أن النتيجة true
expect($result)->toBeTrue();

// التأكد من أن الطلب الخارجي تم إرساله بالـ Headers والبيانات الصحيحة تماماً
Http::assertSent(function ($request) {
return $request->hasHeader('X-Project-Key', '12') &&
$request['user_id'] === 5;
});
});

// 2. اختبار المسار عندما لا يمتلك المستخدم صلاحية الوصول
test('exsists_in_project returns false when microservice denies access', function () {
// محاكاة رد السيرفيس الخارجي بإرجاع false
Http::fake([
'http://localhost:8001/api/check-project-access' => Http::response(['has_access' => false], 200),
]);

$controller = app(ProjectController::class);

$result = $controller->exsists_in_project(userId: 5, projectId: 12);

// التأكيد على أن النتيجة false
expect($result)->toBeFalse();
});