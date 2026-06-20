<?php

namespace Tests\Feature\Services;

use App\Services\JwtService;
use Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Mockery;

beforeEach(function () {
  // إنشاء ملفات مؤقتة حقيقية لمحاكاة المفاتيح
  $this->tmpPrivateKey = tempnam(sys_get_temp_dir(), 'priv_');
  $this->tmpPublicKey  = tempnam(sys_get_temp_dir(), 'pub_');

  // كتابة قيم افتراضية داخل الملفات لتشغيل الوضع الناجح
  file_put_contents($this->tmpPrivateKey, 'test-secret-key-shared-for-symmetric');
  file_put_contents($this->tmpPublicKey, 'test-secret-key-shared-for-symmetric');

  // ضبط الإعدادات بشكل فوري للبيئة التجريبية
  config([
    'jwt.private_key' => $this->tmpPrivateKey,
    'jwt.public_key'  => $this->tmpPublicKey,
    'jwt.issuer'      => 'wevo-auth-service',
    'jwt.algo'        => 'HS256', // نستخدم التشفير التماثلي لتبسيط التيست بدون مفاتيح RSA معقدة
    'jwt.ttl'         => 60,
    'jwt.access_ttl'  => 15,
    'jwt.refresh_ttl' => 10080,
  ]);
});

afterEach(function () {
  // تنظيف النظام وحذف الملفات المؤقتة بعد كل اختبار
  @unlink($this->tmpPrivateKey);
  @unlink($this->tmpPublicKey);
});

// =========================================================================
// 1. اختبارات الـ Constructor والـ Exceptions (تغطية 100% للـ افترضيات)
// =========================================================================

test('constructor throws exception if private key file does not exist', function () {
  config(['jwt.private_key' => 'non_existent_file_path.key']);

  expect(fn() => new JwtService())->toThrow(Exception::class, 'Private key file not found');
});

test('constructor throws exception if public key file does not exist', function () {
  config(['jwt.public_key' => 'non_existent_file_path.key']);

  expect(fn() => new JwtService())->toThrow(Exception::class, 'Public key file not found');
});

test('constructor throws exception if private key is unreadable or empty', function () {
  // تفريغ الملف لجعله غير قابل للقراءة (محتوى فارغ يعود بـ false أو فولد)
  file_put_contents($this->tmpPrivateKey, '');

  expect(fn() => new JwtService())->toThrow(Exception::class, 'Private key could not be read');
});

test('constructor throws exception if public key is unreadable or empty', function () {
  // تفريغ الملف العام
  file_put_contents($this->tmpPublicKey, '');

  expect(fn() => new JwtService())->toThrow(Exception::class, 'Public key could not be read');
});

// =========================================================================
// 2. اختبار دالة: returnInfo
// =========================================================================

test('returnInfo returns array with public and private keys content', function () {
  $jwtService = new JwtService();
  $info = $jwtService->returnInfo();

  expect($info)->toBeArray()
    ->toHaveKeys(['private', 'public'])
    ->and($info['private'])->toBe('test-secret-key-shared-for-symmetric');
});

// =========================================================================
// 3. اختبار دالتي: generateToken & validateToken
// =========================================================================

test('generateToken builds a valid platform access token and validates correctly', function () {
  $jwtService = new JwtService();
  $mockedUser = (object) ['id' => 45];
  $sessionId = 'session_secure_999';

  // 1. توليد التوكن
  $token = $jwtService->generateToken($mockedUser, $sessionId);
  expect($token)->toBeString();

  // 2. التحقق من التوكن وفك تشفيره حقيقةً
  $decoded = $jwtService->validateToken($token);

  expect($decoded)->toBeObject()
    ->and($decoded->sub)->toBe(45)
    ->and($decoded->sid)->toBe($sessionId)
    ->and($decoded->type)->toBe('platform')
    ->and($decoded->iss)->toBe('wevo-auth-service');
});

test('validateToken returns null if token is corrupted or invalid', function () {
  $jwtService = new JwtService();

  $result = $jwtService->validateToken('invalid.corrupted.token-string');
  expect($result)->toBeNull();
});

// =========================================================================
// 4. اختبار دالة: generateRefreshToken
// =========================================================================

test('generateRefreshToken inserts records into DB and creates valid refresh token', function () {
  $jwtService = new JwtService();
  $mockedUser = (object) ['id' => 12];
  $sessionId = 'session_refresh_12';

  // هنا الـ Mock ضروري لمنع الاتصال الحقيقي بقاعدة البيانات لجدول قد لا يكون منشأ
  DB::shouldReceive('table')
    ->once()
    ->with('refresh_tokens')
    ->andReturn(Mockery::self());

  DB::shouldReceive('insert')
    ->once()
    ->with(Mockery::on(function ($argument) use ($mockedUser, $sessionId) {
      return $argument['user_id'] === $mockedUser->id &&
        $argument['session_id'] === $sessionId &&
        isset($argument['token_id']);
    }))
    ->andReturn(true);

  $token = $jwtService->generateRefreshToken($mockedUser, $sessionId);
  expect($token)->toBeString();

  // فحص التوكن الناتج للتأكد من الـ Payload
  $decoded = $jwtService->validateToken($token);
  expect($decoded->type)->toBe('refresh')
    ->and($decoded->sub)->toBe(12);
});

// =========================================================================
// 5. اختبار دوال الـ Services: generateServiceToken & generateServiceRefreshToken
// =========================================================================

test('generateServiceToken creates a token with service type payload', function () {
  $jwtService = new JwtService();
  $mockedService = (object) ['id' => 88];
  $sessionId = 'service_session_88';

  $token = $jwtService->generateServiceToken($mockedService, $sessionId);
  $decoded = $jwtService->validateToken($token);

  expect($decoded->type)->toBe('service')
    ->and($decoded->sub)->toBe(88);
});

test('generateServiceRefreshToken stores data and issues service refresh token', function () {
  $jwtService = new JwtService();
  $mockedService = (object) ['id' => 99];
  $sessionId = 'service_session_99';

  // محاكاة قاعدة البيانات للـ Service Token
  DB::shouldReceive('table')->once()->with('refresh_tokens')->andReturn(Mockery::self());
  DB::shouldReceive('insert')->once()->andReturn(true);

  $token = $jwtService->generateServiceRefreshToken($mockedService, $sessionId);
  $decoded = $jwtService->validateToken($token);

  expect($decoded->type)->toBe('refresh')
    ->and($decoded->sub)->toBe(99);
});
