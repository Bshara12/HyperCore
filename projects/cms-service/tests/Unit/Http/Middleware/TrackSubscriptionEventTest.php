<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\TrackSubscriptionEvent;
use App\Domains\Subscription\Services\DomainEventService;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mockery;

beforeEach(function () {
  // المشروع الحالي يُحفظ كنسخة مفردة في الحاوية؛ تركه يتسرّب بين الاختبارات
  App::forgetInstance('currentProject');
});

/** الهوية تأتي من حيث يضعها AuthUserMiddleware: خصائص الطلب. */
function actAsSubscriber(Request $request, int $id): void
{
  $request->attributes->set('auth_user', ['id' => $id]);
}

/** يثبّت المشروع الحالي كما يفعل ResolveProject قبل هذا الميدلوير. */
function bindCurrentProject(int $id): void
{
  $project = new Project();
  $project->id = $id;

  App::instance('currentProject', $project);
}

// ─── 1. اختبار حالة فشل الطلب (Status >= 400) ──────────────────────────────
test('it does not track event if response status code is 400 or higher', function () {
  // تجهيز الـ Mock والتأكد من أن الدالة dispatch لن تُستدعى أبداً
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldNotReceive('dispatch');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST');

  // محاكاة استجابة خاطئة (مثلاً 400 Bad Request)
  $response = $middleware->handle($request, function ($req) {
    return response()->json(['error' => 'Bad Request'], 400);
  }, 'event.key');

  expect($response->getStatusCode())->toBe(400);
});

// ─── 2. اختبار حالة عدم وجود معرف المستخدم user_id ─────────────────────────
test('it does not track event if user_id is missing from request', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldNotReceive('dispatch');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST'); // طلب فارغ بدون مستخدم

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'event.key');

  expect($response->getStatusCode())->toBe(200);
});

/*
| ─── 3. الهوية المُرسَلة في جسم الطلب لا تُصدَّق ────────────────────────────
|
| user_id في المدخلات يزوّره العميل، فيحرق حصّة غيره أو يتجاوز حصّته هو.
| فالميدلوير يتجاهله تماماً — لا يقرؤه ثم يتحقّق منه.
*/
test('it ignores a user_id forged in the request body', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldNotReceive('dispatch');

  $middleware = new TrackSubscriptionEvent($serviceMock);

  $request = Request::create('/any-route', 'POST', [
    'user_id' => '123',
    'project_id' => '456',
  ]);

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.activated');

  expect($response->getStatusCode())->toBe(200);
});

// ─── 4. الهوية من خصائص الطلب، والمشروع من المشروع الحالي ────────────────
test('it tracks the event for the authenticated user and current project', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldReceive('dispatch')
    ->once()
    ->with(789, 999, 'subscription.updated');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST');

  actAsSubscriber($request, 789);
  bindCurrentProject(999);

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.updated');

  expect($response->getStatusCode())->toBe(200);
});

// ─── 5. غياب المشروع الحالي يُرسَل null لا يُسقط الحدث ────────────────────
test('it tracks event with null project_id if project_id is missing', function () {
  $serviceMock = Mockery::mock(DomainEventService::class);
  $serviceMock->shouldReceive('dispatch')
    ->once()
    ->with(123, null, 'subscription.cancelled');

  $middleware = new TrackSubscriptionEvent($serviceMock);
  $request = Request::create('/any-route', 'POST');

  actAsSubscriber($request, 123);
  // لا مشروع حالي: الطلب بلا X-Project-Key

  $response = $middleware->handle($request, function ($req) {
    return response()->json(['success' => true], 200);
  }, 'subscription.cancelled');

  expect($response->getStatusCode())->toBe(200);
});
