<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // تنظيف مجلد النسخ الاحتياطية قبل كل اختبار لضمان دقة الفحص
    if (file_exists(storage_path('app/backups'))) {
        array_map('unlink', glob(storage_path('app/backups/*')));
        rmdir(storage_path('app/backups'));
    }
    
    // ضبط إعدادات وهمية لقاعدة البيانات لضمان قراءتها داخل دالة handle
    Config::set('database.connections.mysql.database', 'test_db');
    Config::set('database.connections.mysql.username', 'test_user');
    Config::set('database.connections.mysql.password', 'test_pass');
    Config::set('database.connections.mysql.host', '127.0.0.1');
});

it('creates the backup directory if it does not exist and runs backup successfully', function () {
    // 1. التأكد أولاً أن المجلد غير موجود لكي نغطي شرط الـ if (! file_exists)
    expect(file_exists(storage_path('app/backups')))->toBeFalse();

    // 2. تشغيل أمر Artisan بشكل صحيح باستخدام المساعد المتوفر في بيئة الاختبار
    $this->artisan('db:backup')
        ->expectsOutputToContain('Backup created:')
        ->assertSuccessful();

    // 3. التحقق من أن الكود قام بإنشاء المجلد بنجاح
    expect(file_exists(storage_path('app/backups')))->toBeTrue();
});