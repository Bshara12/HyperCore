<?php

namespace Tests\Unit\Models;

use App\Models\ServiceClient;
use App\Models\ServiceSession;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
  Schema::dropIfExists('service_sessions');
  Schema::dropIfExists('service_clients');

  // 1. بناء جدول عملاء الخدمة
  Schema::create('service_clients', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('client_id');
    $table->string('client_secret');
    $table->timestamps();
  });

  // 2. بناء جدول الجلسات بناءً على الميجريشن الحقيقي الخاص بك تماماً 🎯
  Schema::create('service_sessions', function (Blueprint $table) {
    $table->char('id', 26)->primary(); // هنا استعملنا char وليس id() الافتراضي
    $table->foreignId('service_client_id')->constrained('service_clients')->onDelete('cascade');
    $table->string('client_id')->nullable();
    $table->dateTime('last_activity_at')->nullable();
    $table->dateTime('expires_at');
    $table->dateTime('revoked_at')->nullable();
    $table->timestamps();
  });
});

// ─── كود الفحص والتأكيدات ──────────────────────────────────────────────────

test('it can create a service client with fillable attributes', function () {
  $clientData = [
    'name'          => 'Mobile Application Client',
    'client_id'     => 'client_id_abc123',
    'client_secret' => 'super_secret_key_xyz789',
  ];

  $client = ServiceClient::create($clientData);

  expect($client->exists)->toBeTrue();
  expect($client->name)->toBe($clientData['name']);
});


test('a service client has many sessions', function () {
  $client = ServiceClient::create([
    'name'          => 'Web Frontend',
    'client_id'     => 'web_001',
    'client_secret' => 'secret_001',
  ]);

  // الجلسة الأولى: توليد معرف ULID عشوائي متوافق مع طول الـ 26 حرفاً وتمرير تاريخ الانتهاء
  $session1 = new ServiceSession();
  $session1->id = (string) Str::ulid(); // توليد الـ ULID يدوياً للتست
  $session1->service_client_id = $client->id;
  $session1->expires_at = now()->addDays(7);
  $session1->save();

  // الجلسة الثانية
  $session2 = new ServiceSession();
  $session2->id = (string) Str::ulid();
  $session2->service_client_id = $client->id;
  $session2->expires_at = now()->addDays(7);
  $session2->save();

  // التأكيد المشدد على نجاح العلاقة واسترجاع البيانات
  expect($client->sessions)->toHaveCount(2);
  expect($client->sessions->first()->service_client_id)->toBe($client->id);
  expect(strlen($client->sessions->first()->id))->toBe(26); // التأكد من أن المعرف طوله 26 حرفاً بالفعل
});
