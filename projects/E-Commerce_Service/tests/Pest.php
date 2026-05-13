<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// هنا نقوم بربط Pest بكلاس TestCase الخاص بلارافيل لجميع المجلدات
// هذا يضمن عمل Http::fake() و Config::set() بشكل صحيح
pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations / Helpers
|--------------------------------------------------------------------------
*/

// يمكنك ترك التمديدات (Expectations) كما هي أو حذفها إذا لم تحتجها حالياً
expect()->extend('toBeOne', function () {
  return $this->toBe(1);
});

// هنا يمكنك إضافة دوال مساعدة عالمية (Global Helpers)
// مثلاً: دالة لتسجيل الدخول بسرعة في الاختبارات
function loginAsAdmin()
{
  // logic to return a logged in user
}
