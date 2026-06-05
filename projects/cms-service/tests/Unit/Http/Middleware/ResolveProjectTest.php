<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ResolveProject;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

// ─── 1. اختبار حالة عدم إرسال أي هيدر (خطأ 400) ─────────────────────────────
test('it aborts with 400 if both X-Project-Key and X-Project-Id headers are missing', function () {
  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');

  // 🔥 تم الإصلاح: استخدام toThrow بدلاً من throws
  expect(fn() => $middleware->handle($request, function ($req) {}))
    ->toThrow(HttpException::class, 'X-Project-Key or X-Project-Id header is required');
});

// ─── 2. اختبار جلب المشروع بنجاح عبر الرقم ID (خطأ 200) ────────────────────────
test('it resolves project by numeric ID successfully and binds it to container', function () {
  $project = Project::factory()->create();

  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');
  $request->headers->set('X-Project-Id', $project->id);

  $response = $middleware->handle($request, function ($req) {
    return response('Next was called');
  });

  expect($response->getContent())->toBe('Next was called');
  expect(app('currentProject')->id)->toBe($project->id);
});

// ─── 3. اختبار عدم وجود المشروع عند البحث بالـ ID (خطأ 404) ───────────────────
test('it aborts with 404 if numeric project ID is not found', function () {
  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');
  $request->headers->set('X-Project-Id', 99999);

  // 🔥 تم الإصلاح: استخدام toThrow
  expect(fn() => $middleware->handle($request, function ($req) {}))
    ->toThrow(NotFoundHttpException::class, 'Project not found');
});

// ─── 4. اختبار جلب المشروع عبر الـ Public ID (خطأ 200) ───────────────────────
test('it resolves project by public_id successfully and binds it to container', function () {
  // نترك الفاكتوري ينشئ البيانات بطبيعته لتجنب تعارض الـ Observers
  $project = Project::factory()->create();

  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');
  $request->headers->set('X-Project-Key', $project->public_id);

  $middleware->handle($request, function ($req) {});

  expect(app('currentProject')->id)->toBe($project->id);
});

// ─── 5. اختبار جلب المشروع عبر الـ Slug (خطأ 200) ───────────────────────────
test('it resolves project by slug successfully and binds it to container', function () {
  $project = Project::factory()->create();

  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');
  $request->headers->set('X-Project-Key', $project->slug);

  $middleware->handle($request, function ($req) {});

  expect(app('currentProject')->id)->toBe($project->id);
});

// ─── 6. اختبار عدم وجود المشروع عند البحث بالنص (خطأ 404) ─────────────────────
test('it aborts with 404 if project key string is not found', function () {
  $middleware = new ResolveProject();
  $request = Request::create('/any-route', 'GET');
  $request->headers->set('X-Project-Key', 'non-existent-slug-or-key');

  // 🔥 تم الإصلاح: استخدام toThrow
  expect(fn() => $middleware->handle($request, function ($req) {}))
    ->toThrow(NotFoundHttpException::class, 'Project not found');
});
