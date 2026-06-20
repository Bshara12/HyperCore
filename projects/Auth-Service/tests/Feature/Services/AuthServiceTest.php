<?php

namespace Tests\Feature\Services;

use App\Events\SystemLogEvent;
use App\Jobs\SendOTPMailJob;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\JwtService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;

beforeEach(function () {
  // 1. تزييف المكونات التي تطلق تأثيرات جانبية خارجية (حرصاً على عدم إرسال بريد أو تسجيل نظام فعلي)
  Event::fake();
  Bus::fake();

  // 2. معالجة الـ Logs ذكياً: السماح بمرورها كوضع افتراضي في كل التيستات دون كسر الكود
  Log::shouldReceive('info')->byDefault();

  // 3. بناء الـ Mocks للـ Dependencies الأساسية فقط لأنها تتعامل مع قاعدة البيانات وجلسات التشفير
  $this->userRepositoryMock = mock(UserRepositoryInterface::class);
  $this->jwtServiceMock = mock(JwtService::class);

  $this->authService = new AuthService($this->userRepositoryMock, $this->jwtServiceMock);
});

// =========================================================================
// 1. اختبار دالة: registerService
// =========================================================================

test('registerService successfully creates a user, generates OTP, logs, and dispatches event', function () {
  $inputData = [
    'name'     => 'Ahmad',
    'email'    => 'ahmad@wevo.app',
    'password' => 'secret123'
  ];

  $mockedUser = new User();
  $mockedUser->id = 1;
  $mockedUser->email = 'ahmad@wevo.app';

  $this->userRepositoryMock->shouldReceive('create')
    ->once()
    ->andReturn($mockedUser);

  $this->userRepositoryMock->shouldReceive('update')
    ->once()
    ->with($mockedUser, Mockery::type('array'));

  // هنا استعملنا الـ Mock للـ Log عند الحاجة والضرورة القصوى للتحقق من التسجيل المزدوج
  Log::shouldReceive('info')
    ->times(2)
    ->with('Auth Service Action', Mockery::type('array'));

  $result = $this->authService->registerService($inputData);

  expect($result)->toBeInstanceOf(User::class);
  Bus::assertDispatched(SendOTPMailJob::class);
  Event::assertDispatched(SystemLogEvent::class);
});

// =========================================================================
// 2. اختبار دالة: verifyOTP
// =========================================================================

test('verifyOTP returns false if user has no otp or mismatch', function () {
  $user = new User();
  $user->otp_code = '123456';

  expect($this->authService->verifyOTP($user, '654321'))->toBeFalse();

  $user->otp_code = null;
  expect($this->authService->verifyOTP($user, '123456'))->toBeFalse();
});

test('verifyOTP returns false if otp code is expired', function () {
  $user = new User();
  $user->otp_code = '123456';
  $user->otp_expires_at = now()->subMinute();

  expect($this->authService->verifyOTP($user, '123456'))->toBeFalse();
});

test('verifyOTP successfully verifies a valid code and updates user fields', function () {
  $user = new User();
  $user->id = 1;
  $user->otp_code = '123456';
  $user->otp_expires_at = now()->addMinutes(5);

  $this->userRepositoryMock->shouldReceive('update')
    ->once()
    ->with($user, [
      'is_verified'    => true,
      'otp_code'       => null,
      'otp_expires_at' => null,
    ]);

  expect($this->authService->verifyOTP($user, '123456'))->toBeTrue();
});

// =========================================================================
// 3. اختبار دالة: attemptLogin
// =========================================================================

test('attemptLogin returns credentials error if user is not found', function () {
  $this->userRepositoryMock->shouldReceive('findByEmail')
    ->once()
    ->with('notfound@wevo.app')
    ->andReturn(null);

  $result = $this->authService->attemptLogin('notfound@wevo.app', 'password');

  expect($result)->toEqual(['success' => false, 'message' => 'Invalid credentials']);
});

test('attemptLogin returns locked error if account lock time is active', function () {
  $user = new User();
  $user->locked_until = now()->addMinutes(10);

  $this->userRepositoryMock->shouldReceive('findByEmail')->andReturn($user);

  $result = $this->authService->attemptLogin('locked@wevo.app', 'password');

  expect($result['success'])->toBeFalse();
  expect($result['message'])->toStartWith('Account locked until');
});

test('attemptLogin increments failed attempts on wrong password and locks if reached 3', function () {
  $user = new User();
  $user->id = 5;
  $user->password = Hash::make('correct_password');
  $user->failed_attempts = 2;

  $this->userRepositoryMock->shouldReceive('findByEmail')->andReturn($user);

  $this->userRepositoryMock->shouldReceive('update')
    ->once()
    ->with($user, Mockery::on(function ($updateData) {
      return isset($updateData['locked_until']) && $updateData['failed_attempts'] === 0;
    }));

  $result = $this->authService->attemptLogin('user@wevo.app', 'wrong_password');
  expect($result['success'])->toBeFalse();
});

test('attemptLogin increments failed attempts without locking if below 3', function () {
  $user = new User();
  $user->id = 5;
  $user->password = Hash::make('correct_password');
  $user->failed_attempts = 0;

  $this->userRepositoryMock->shouldReceive('findByEmail')->andReturn($user);

  $this->userRepositoryMock->shouldReceive('update')
    ->once()
    ->with($user, ['failed_attempts' => 1]);

  $result = $this->authService->attemptLogin('user@wevo.app', 'wrong_password');
  expect($result['success'])->toBeFalse();
});

test('attemptLogin clears logs and passes on successful credentials matching', function () {
  $user = new User();
  $user->id = 5;
  $user->password = Hash::make('golden_password');
  $user->failed_attempts = 1;

  $this->userRepositoryMock->shouldReceive('findByEmail')->andReturn($user);

  $this->userRepositoryMock->shouldReceive('update')
    ->once()
    ->with($user, ['failed_attempts' => 0, 'locked_until' => null]);

  $result = $this->authService->attemptLogin('user@wevo.app', 'golden_password');

  expect($result['success'])->toBeTrue();
  expect($result['user'])->toBe($user);
});

// =========================================================================
// 4. اختبار دالة: logoutService
// =========================================================================

test('logoutService throws exception if token is invalid', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')
    ->once()
    ->with('invalid_token')
    ->andReturn(null);

  expect(fn() => $this->authService->logoutService('invalid_token', []))
    ->toThrow(Exception::class, 'Invalid token');
});

test('logoutService throws exception if session id sid is missing in payload', function () {
  $payloadWithoutSid = (object) ['user_id' => 1];

  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn($payloadWithoutSid);

  expect(fn() => $this->authService->logoutService('valid_token', []))
    ->toThrow(Exception::class, 'Session ID missing');
});

test('logoutService successfully revokes token when payload and sid are valid', function () {
  $validPayload = (object) ['sid' => 'session_xyz_123'];

  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn($validPayload);

  $this->userRepositoryMock->shouldReceive('revoke')
    ->once()
    ->with('token123', ['decoded_data']);

  $this->authService->logoutService('token123', ['decoded_data']);
});

// =========================================================================
// 5. اختبار دالتي: changePassword & getUsersByIds
// =========================================================================

test('changePassword throws exception if current password check fails', function () {
  $user = new User();
  $user->password = Hash::make('real_old_pass');

  $data = [
    'user'             => $user,
    'current_password' => 'wrong_old_pass',
    'new_password'     => 'new_pass_123'
  ];

  expect(fn() => $this->authService->changePassword($data))
    ->toThrow(Exception::class, 'Current password is incorrect');
});

test('changePassword updates password successfully when verification passes', function () {
  $user = new User();
  $user->id = 77;
  $user->password = Hash::make('correct_old_pass');

  $data = [
    'user'             => $user,
    'current_password' => 'correct_old_pass',
    'new_password'     => 'super_new_pass_999'
  ];

  $this->userRepositoryMock->shouldReceive('updatePassword')
    ->once()
    ->with(77, Mockery::type('string'));

  $this->authService->changePassword($data);
});

test('getUsersByIds filters out duplicates and fetches items via repository', function () {
  $inputIds = [1, 2, 2, 3];

  // تحديد المصفوفة الناتجة مع الحفاظ على مفاتيح PHP الأصلية بعد الـ array_unique
  $filteredIds = [0 => 1, 1 => 2, 3 => 3];

  $expectedCollection = collect([new User(), new User()]);

  $this->userRepositoryMock->shouldReceive('getUsersByIds')
    ->once()
    ->with($filteredIds)
    ->andReturn($expectedCollection);

  $result = $this->authService->getUsersByIds($inputIds);
  expect($result)->toBe($expectedCollection);
});
