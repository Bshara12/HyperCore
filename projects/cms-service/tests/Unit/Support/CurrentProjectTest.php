<?php

namespace Tests\Unit\Support;

use App\Support\CurrentProject;
use App\Models\Project;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  // تنظيف الحاوية قبل كل فحص لضمان عدم تداخل البيانات بين الاختبارات
  if (app()->bound('currentProject')) {
    app()->offsetUnset('currentProject');
  }
});

// ─── كود الاختبار والتغطية الشاملة ───────────────────────────────────────

// 1. اختبار حالة عدم وجود المشروع في الحاوية (تغطية سطر abort)
test('it aborts with 500 status when current project is not bound in the container', function () {
  expect(function () {
    CurrentProject::get();
  })->toThrow(HttpException::class, 'Current project is not resolved');

  // تأكيد إضافي أن رمز الحالة هو 500
  try {
    CurrentProject::get();
  } catch (HttpException $e) {
    expect($e->getStatusCode())->toBe(500);
  }
});


// 2. اختبار استرجاع كائن المشروع بنجاح (تغطية دالة get)
test('it returns the currently bound project instance from the container', function () {
  // إنشاء كائن حقيقي نقي لـ Project وتعيين معرّف له بدون حفظه في قاعدة البيانات لتسريع التست
  $project = new Project();
  $project->id = 55;

  // ربط الكائن داخل الحاوية يدوياً
  app()->instance('currentProject', $project);

  // استدعاء الكلاس الفعلي
  $resolvedProject = CurrentProject::get();

  expect($resolvedProject)->toBeInstanceOf(Project::class);
  expect($resolvedProject->id)->toBe(55);
});


// 3. اختبار جلب معرّف المشروع مباشرة (تغطية دالة id)
test('it returns the correct project id from the current project', function () {
  $project = new Project();
  $project->id = 999;

  app()->instance('currentProject', $project);

  // التأكد من أن دالة id() تقرأ القيمة بشكل سليم
  expect(CurrentProject::id())->toBe(999);
});
