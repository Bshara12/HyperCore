<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

beforeEach(function () {
  // 1. إنشاء جداول وهمية في الذاكرة لتشغيل الاختبار عليها بشكل معزول
  Schema::create('logs', function (Blueprint $table) {
    $table->id();
    $table->string('event_id')->unique();
    $table->string('module');
    $table->string('event_type');
    $table->integer('user_id')->nullable();
    $table->string('entity_type')->nullable();
    $table->integer('entity_id')->nullable();
    $table->integer('project_id')->nullable();
    $table->text('metadata')->nullable();
    $table->string('correlation_id')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();
  });

  Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('event_id')->unique();
    $table->string('module');
    $table->string('entity_type')->nullable();
    $table->integer('entity_id')->nullable();
    $table->text('old_values')->nullable();
    $table->text('new_values')->nullable();
    $table->integer('user_id')->nullable();
    $table->string('correlation_id')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();
  });
});

afterEach(function () {
  // 2. تنظيف البيئة والمحاكاة بعد نهاية الاختبار
  Schema::dropIfExists('logs');
  Schema::dropIfExists('audit_logs');
  Mockery::close();
});

it('consumes and processes different types of rabbitmq messages successfully', function () {
  // 3. عمل Mock للـ Channel للتحكم بآلية الـ Callback وكسر الحلقة اللانهائية
  $mockChannel = Mockery::mock(AMQPChannel::class);

  $mockChannel->shouldReceive('queue_declare')->once()->with('logs_queue', false, true, false, false);
  $mockChannel->shouldReceive('basic_qos')->once()->with(0, 1, false);

  $callback = null;
  $mockChannel->shouldReceive('basic_consume')
    ->once()
    ->with('logs_queue', '', false, false, false, false, Mockery::on(function ($closure) use (&$callback) {
      $callback = $closure;
      return true;
    }));

  // إجبار دالة wait() على رمي Exception لكسر الحلقة اللانهائية (for (;;))
  $mockChannel->shouldReceive('wait')
    ->once()
    ->andThrow(new \RuntimeException('Break Loop'));

  // 4. محاكاة الـ Connection وحقنه في الحاوية
  $mockConnection = Mockery::mock('overload:' . AMQPStreamConnection::class);
  $mockConnection->shouldReceive('channel')->once()->andReturn($mockChannel);

  // كتم جملة "Waiting for messages..." عند بداية تشغيل الأمر
  ob_start();
  try {
    $this->artisan('consume:logs');
  } catch (\RuntimeException $e) {
    expect($e->getMessage())->toBe('Break Loop');
  }
  ob_end_clean();

  expect($callback)->toBeInstanceOf(\Closure::class);

  // -------------------------------------------------------------
  // سيناريوهات الاختبار والفحص عبر الـ Output Buffering
  // -------------------------------------------------------------

  // 5. سيناريو الاختبار الأول: رسالة غير صالحة (Invalid JSON Message)
  $invalidMsg = new AMQPMessage('invalid-json');
  $invalidMsg->delivery_info['delivery_tag'] = 'tag-1';
  $mockChannel->shouldReceive('basic_ack')->once()->with('tag-1');

  ob_start();
  $callback($invalidMsg);
  $output = ob_get_clean();
  expect($output)->toContain("Invalid message");

  // 6. سيناريو الاختبار الثاني: رسالة عادية (Log Event) وحفظها بنجاح
  $logData = [
    'event_id' => 'evt_1',
    'module' => 'auth',
    'event_type' => 'login',
    'occurred_at' => now()->toDateTimeString()
  ];
  $logMsg = new AMQPMessage(json_encode($logData));
  $logMsg->delivery_info['delivery_tag'] = 'tag-2';

  // نتوقع استدعاؤها مرتين لأننا سنمرر نفس الـ tag-2 في سيناريو الـ Duplicate أيضاً
  $mockChannel->shouldReceive('basic_ack')->twice()->with('tag-2');

  ob_start();
  $callback($logMsg);
  $output = ob_get_clean();
  expect($output)->toContain("Log saved")
    ->and(DB::table('logs')->count())->toBe(1);

  // 7. سيناريو الاختبار الثالث: رسالة مكررة (Duplicate Log) لتشغيل الـ else الخاصة بـ insertOrIgnore
  ob_start();
  $callback($logMsg);
  $output = ob_get_clean();
  expect($output)->toContain("Duplicate log skipped")
    ->and(DB::table('logs')->count())->toBe(1);

  // 8. سيناريو الاختبار الرابع: رسالة تدقيق (Audit Log Event) وحفظها بنجاح
  $auditData = [
    'event_id' => 'audit_1',
    'module' => 'users',
    'event_type' => 'audit',
    'occurred_at' => now()->toDateTimeString(),
    'old_values' => ['role' => 'user'],
    'new_values' => ['role' => 'admin']
  ];
  $auditMsg = new AMQPMessage(json_encode($auditData));
  $auditMsg->delivery_info['delivery_tag'] = 'tag-3';

  // نتوقع استدعاؤها مرتين بسبب التكرار في الخطوة التالية
  $mockChannel->shouldReceive('basic_ack')->twice()->with('tag-3');

  ob_start();
  $callback($auditMsg);
  $output = ob_get_clean();
  expect($output)->toContain("Audit log saved")
    ->and(DB::table('audit_logs')->count())->toBe(1);

  // 9. سيناريو الاختبار الخامس: رسالة مكررة لـ Audit Log لتشغيل الـ else الخاصة بـ audit_logs
  ob_start();
  $callback($auditMsg);
  $output = ob_get_clean();
  expect($output)->toContain("Duplicate audit event skipped")
    ->and(DB::table('audit_logs')->count())->toBe(1);

  // 10. سيناريو الاختبار السادس: اختبار الـ Catch Block لرمي Throwable (السطر 123)
  $brokenData = [
    'event_id' => 'evt_broken',
    'module' => 'auth',
    'event_type' => 'login',
    // تمرير مصفوفة بدلاً من نص عادي في حقل التاريخ لإجبار الـ Database Driver على رمي خطأ برمي استثناء
    'occurred_at' => ['invalid_date_format_array']
  ];
  $brokenMsg = new AMQPMessage(json_encode($brokenData));
  $brokenMsg->delivery_info['delivery_tag'] = 'tag-4';
  $mockChannel->shouldReceive('basic_ack')->once()->with('tag-4');

  ob_start();
  $callback($brokenMsg);
  $output = ob_get_clean();

  // التحقق من أن الـ catch التقطت الخطأ بنجاح وطبعت المخرج المطلوب لضمان قفل الـ 100%
  expect($output)->toContain('Error processing message:');
});
