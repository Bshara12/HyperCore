<?php

use App\Models\ServiceClient;
use App\Services\JwtService;
use App\Services\SessionService;
use App\Http\Controllers\ServiceAuthController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase; // ✅ استيراد ترحيل قاعدة البيانات
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ✅ تفعيل تهيئة وقص قاعدة البيانات تلقائياً قبل كل فحص
uses(RefreshDatabase::class);

// =========================================================================
// 1. اختبار التابع: createService
// =========================================================================

test('createService successfully creates a new service client', function () {
  $payload = [
    'name' => 'Payment Gateway Service',
    'client_secret' => 'super-secure-secret-key',
  ];

  // إرسال الطلب إلى الـ API الخاص بإنشاء الخدمة
  $response = $this->postJson('/api/create-service', $payload);

  // التأكد من نجاح العملية وإنشاء الكيان في قاعدة البيانات
  $response->assertSuccessful();

  $this->assertDatabaseHas('service_clients', [
    'name' => 'Payment Gateway Service',
  ]);

  // التأكد من أن الـ client_id تم إنشاؤه بصيغة ULID والـ secret تم تشفيره
  $client = ServiceClient::where('name', 'Payment Gateway Service')->first();
  expect($client->client_id)->not->toBeNull()
    ->and(Hash::check('super-secure-secret-key', $client->client_secret))->toBeTrue();
});


// =========================================================================
// 2. اختبار التابع: token (توليد توكن الخدمة)
// =========================================================================

test('token method returns access token with valid credentials', function () {
  // إنشاء خدمة داخل قاعدة البيانات للتفقد منها
  $client = ServiceClient::create([
    'name' => 'Auth Service',
    'client_id' => 'ulid-client-123',
    'client_secret' => Hash::make('secret123')
  ]);

  // عمل Mock للسيرفس الخاصة بالجلسات والـ JWT
  $this->mock(SessionService::class, function ($mock) {
    $mock->shouldReceive('createServiceSession')->once()->andReturn('fake-session-id');
  });

  $this->mock(JwtService::class, function ($mock) {
    $mock->shouldReceive('generateServiceToken')->once()->andReturn('fake-jwt-access-token');
  });

  $response = $this->postJson('/api/service/token', [
    'client_id' => 'ulid-client-123',
    'client_secret' => 'secret123'
  ]);

  $response->assertStatus(200)
    ->assertJson(['access_token' => 'fake-jwt-access-token']);
});

test('token method returns 401 if client does not exist', function () {
  $response = $this->postJson('/api/service/token', [
    'client_id' => 'non-existent-client',
    'client_secret' => 'any-secret'
  ]);

  $response->assertStatus(401)
    ->assertJson(['error' => 'Invalid client']);
});

test('token method returns 401 if client secret is invalid', function () {
  $client = ServiceClient::create([
    'name' => 'Notification Service',
    'client_id' => 'ulid-client-456',
    'client_secret' => Hash::make('correct-secret')
  ]);

  $response = $this->postJson('/api/service/token', [
    'client_id' => 'ulid-client-456',
    'client_secret' => 'wrong-secret' // كلمة مرور خاطئة
  ]);

  $response->assertStatus(401)
    ->assertJson(['error' => 'Invalid secret']);
});


// =========================================================================
// 3. اختبار التابع: validateToken (الدالة الداخلية للكنترولر)
// =========================================================================

test('validateToken returns null when an exception occurs or token is invalid', function () {
  // تأمين مجلد ومفتاح وهمي في الـ storage لضمان عدم حدوث خطأ ملف غير موجود الفعلي
  $keysPath = storage_path('keys');
  if (!file_exists($keysPath)) {
    mkdir($keysPath, 0777, true);
  }
  file_put_contents($keysPath . '/public.key', 'dummy-public-key-content');

  $controller = app(ServiceAuthController::class);

  // تمرير توكن تالف تماماً لفرض الدخول في كتل الـ catch وإرجاع null
  $result = $controller->validateToken('completely-invalid-raw-token');

  expect($result)->toBeNull();
});


// =========================================================================
// 4. اختبار التابع: getService (تمت إضافة closure لتعطيل الميدل وير)
// =========================================================================

test('getService returns service data when authorized with valid token', function () {
  // تعطيل الميدل وير الخارجي لكي يصل الطلب مباشرة إلى منطق الكنترولر ويقرأ الـ Mock
  $this->withoutMiddleware();

  $client = ServiceClient::create([
    'name' => 'Reporting Service',
    'client_id' => 'ulid-client-789',
    'client_secret' => Hash::make('secret')
  ]);

  // عمل Mock للـ JwtService لتقوم بفك التوكن بنجاح وإرجاع الـ sub المطابق للمعرف
  $this->mock(JwtService::class, function ($mock) use ($client) {
    $decodedObject = (object)['sub' => $client->id];
    $mock->shouldReceive('validateToken')->andReturn($decodedObject);
  });

  $response = $this->withToken('valid-bearer-token')
    ->getJson('/api/get-service');

  $response->assertStatus(200)
    ->assertJsonStructure([
      'data' => ['id', 'name', 'client_id', 'sessions']
    ]);
});

test('getService returns 401 Unauthorized when token validation fails', function () {
  // تعطيل الميدل وير الخارجي لكي نتحقق من رسالة الـ Unauthorized الخاصة بالكنترولر نفسه
  $this->withoutMiddleware();

  // محاكاة فشل التحقق من التوكن وإرجاع null
  $this->mock(JwtService::class, function ($mock) {
    $mock->shouldReceive('validateToken')->andReturn(null);
  });

  $response = $this->withToken('invalid-or-expired-token')
    ->getJson('/api/get-service');

  $response->assertStatus(401)
    ->assertJson(['message' => 'Unauthorized']);
});

test('validateToken returns decoded object when token is valid and signed correctly', function () {
  // 1. توليد زوج مفاتيح RSA بطول 2048 بت لتلبية شروط الأمان للمكتبة
  $config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048, // ✅ تم التعديل من 1024 إلى 2048
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
  ];
  $res = openssl_pkey_new($config);
  openssl_pkey_export($res, $privateKey);
  $pubKeyDetails = openssl_pkey_get_details($res);
  $publicKey = $pubKeyDetails["key"];

  // 2. حفظ المفتاح العام في المسار المتوقع
  $keysPath = storage_path('keys');
  if (!file_exists($keysPath)) {
    mkdir($keysPath, 0777, true);
  }
  file_put_contents($keysPath . '/public.key', $publicKey);

  // 3. بناء الـ Payload وتوليد توكن حقيقي مطابق لمعايير RS256
  $payload = [
    'sub' => 'service-123',
    'name' => 'Valid Microservice',
    'iat' => time(),
    'exp' => time() + 3600
  ];

  $validToken = JWT::encode($payload, $privateKey, 'RS256');

  // 4. استدعاء التابع مباشرة من الكنترولر
  $controller = app(ServiceAuthController::class);
  $result = $controller->validateToken($validToken);

  // 5. التأكيد على نجاح فك التشفير وعودة البيانات كاملة
  expect($result)->not->toBeNull()
    ->and($result->sub)->toBe('service-123')
    ->and($result->name)->toBe('Valid Microservice');
});
