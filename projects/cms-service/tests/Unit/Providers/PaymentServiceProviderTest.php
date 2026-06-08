<?php

namespace Tests\Unit\Providers;

use App\Providers\PaymentServiceProvider;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Gateways\StripeGateway;
use App\Domains\Payment\Gateways\PaypalGateway;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Domains\Payment\Repositories\EloquentPaymentRepository;

// 💡 تم إزالة RefreshDatabase هنا لأنه لا توجد عمليات قاعدة بيانات، لتسريع التست وحمايته تماماً من مشاكل الـ PDO
beforeEach(function () {
  $this->provider = new PaymentServiceProvider(app());
  $this->provider->register();
});

// 1. اختبار ربط المستودع (Repository Binding)
test('it binds payment repository interface to eloquent implementation', function () {
  $repository = app()->make(PaymentRepositoryInterface::class);

  expect($repository)->toBeInstanceOf(EloquentPaymentRepository::class);
});

// 2. اختبار الانتقال لبوابة Stripe عند تمرير المعامل الخاص بها
test('it resolves to StripeGateway when stripe is passed as parameter', function () {
  $gateway = app()->make(PaymentGatewayInterface::class, ['gatewayName' => 'stripe']);

  expect($gateway)->toBeInstanceOf(StripeGateway::class);
});

// 3. اختبار الانتقال لبوابة Paypal عند تمرير المعامل الخاص بها
test('it resolves to PaypalGateway when paypal is passed as parameter', function () {
  $gateway = app()->make(PaymentGatewayInterface::class, ['gatewayName' => 'paypal']);

  expect($gateway)->toBeInstanceOf(PaypalGateway::class);
});

// 4. اختبار الاعتماد على البوابة الافتراضية من ملف الـ Config عند غياب المعاملات
test('it resolves to default gateway from config when no parameter is provided', function () {
  // محاكاة وضع Stripe كخيار افتراضي في الـ Config
  config(['payment.default' => 'stripe']);
  $gateway = app()->make(PaymentGatewayInterface::class);
  expect($gateway)->toBeInstanceOf(StripeGateway::class);

  // محاكاة وضع Paypal كخيار افتراضي في الـ Config
  config(['payment.default' => 'paypal']);

  // 🔥 تم حذف forgetInstances الحاوية ستقرأ قيمة الـ config المحدثة الآن بنجاح وبأمان تام
  $gateway = app()->make(PaymentGatewayInterface::class);
  expect($gateway)->toBeInstanceOf(PaypalGateway::class);
});

// 5. اختبار رمي الاستثناء (Exception) عند تمرير بوابة غير مدعومة لتغطية فرع default
test('it throws an invalid argument exception for unsupported gateways', function () {
  expect(function () {
    app()->make(PaymentGatewayInterface::class, ['gatewayName' => 'invalid-gateway']);
  })->toThrow(\InvalidArgumentException::class, 'Unsupported payment gateway: [invalid-gateway]');
});
