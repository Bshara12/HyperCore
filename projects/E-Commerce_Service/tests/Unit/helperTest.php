<?php

it('returns the auth user from request attributes', function () {
  // 1. إنشاء كائن مستخدم وهمي (أو مصفوفة تمثله)
  $mockUser = (object) [
    'id' => 1,
    'name' => 'Gemini User',
    'email' => 'user@example.com'
  ];

  // 2. الحصول على كائن الطلب الحالي في Laravel
  $request = app('request');

  // 3. وضع المستخدم داخل الـ attributes كما تفعل الـ Middleware عادةً
  $request->attributes->set('auth_user', $mockUser);

  // 4. استدعاء الدالة المساعدة والتحقق من النتيجة
  $result = authUser();

  expect($result)->toBe($mockUser)
    ->and($result->id)->toBe(1)
    ->and($result->name)->toBe('Gemini User');
});

it('returns null if auth_user attribute is not set', function () {
  // التأكد من أن الـ attribute غير موجود
  app('request')->attributes->remove('auth_user');

  expect(authUser())->toBeNull();
});
