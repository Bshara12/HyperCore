<?php

namespace Tests\Unit\Domains\Core\Traits;

use App\Domains\Core\Traits\HasProjectHeaders;
use Illuminate\Http\Request;

// إنشاء كلاس وهمي لاستخدام الـ Trait
class HeaderTraitUser
{
  use HasProjectHeaders;
}

beforeEach(function () {
  $this->traitUser = new HeaderTraitUser();
});

it('returns headers with explicit project id', function () {
  // محاكاة وجود توكن في الطلب
  $request = Request::create('/', 'GET');
  $request->headers->set('Authorization', 'Bearer test-token');
  app()->instance('request', $request);

  $headers = $this->traitUser->projectHeaders('123');

  expect($headers)->toBe([
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'X-Project-Id' => '123',
    'Authorization' => 'Bearer test-token',
  ]);
});

it('returns headers using project id from request header when not provided', function () {
  // محاكاة طلب يحتوي على X-Project-Id و Authorization
  $request = Request::create('/', 'GET');
  $request->headers->set('X-Project-Id', '456');
  $request->headers->set('Authorization', 'Bearer request-token');
  app()->instance('request', $request);

  $headers = $this->traitUser->projectHeaders(); // بدون تمرير معامل

  expect($headers['X-Project-Id'])->toBe('456')
    ->and($headers['Authorization'])->toBe('Bearer request-token');
});

it('handles null values gracefully when no header or token is present', function () {
  // طلب فارغ تماماً
  $request = Request::create('/', 'GET');
  app()->instance('request', $request);

  $headers = $this->traitUser->projectHeaders();

  expect($headers['X-Project-Id'])->toBeNull()
    ->and($headers['Authorization'])->toBe('Bearer ');
});
