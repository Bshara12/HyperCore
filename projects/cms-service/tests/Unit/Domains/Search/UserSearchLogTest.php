<?php

namespace Tests\Unit\Domains\Search;

use App\Domains\Search\Models\UserSearchLog;
use App\Domains\Search\Models\UserClickLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

// uses(RefreshDatabase::class);

// test('it has clicks relationship', function () {
//     // 1. إنشاء سجل البحث يدويًا وبشكل مباشر في قاعدة البيانات
//     $searchLog = UserSearchLog::create([
//         'user_id'           => 1,
//         'project_id'        => 1,
//         'keyword'           => 'بحث تجريبي',
//         'language'          => 'ar',
//         'session_id'        => 'session_test_123',
//         'searched_at'       => now(),
//     ]);

//     // 2. إنشاء 3 نقرات مرتبطة بسجل البحث عبر العلاقة مباشرة
//     for ($i = 1; $i <= 3; $i++) {
//         $searchLog->clicks()->create([
//             'user_id'         => 1,
//             'project_id'      => 1,
//             'entry_id'        => 1,
//             'data_type_id'    => 1,
//             'result_position' => $i,
//             'session_id'      => 'session_test_123',
//             'clicked_at'      => now(),
//         ]);
//     }

//     // 3. التأكد من نجاح العلاقة والعدد
//     expect($searchLog->refresh()->clicks)->toHaveCount(3)
//         ->and($searchLog->clicks->first())->toBeInstanceOf(UserClickLog::class);
// });

// test('it casts searched_at to datetime', function () {
//     // إنشاء السجل يدويًا لاختبار الـ Cast
//     $log = UserSearchLog::create([
//         'user_id'     => 1,
//         'project_id'  => 1,
//         'keyword'     => 'test',
//         'searched_at' => '2026-05-24 19:10:00',
//     ]);

//     expect($log->searched_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
//         ->and($log->searched_at->format('H:i'))->toBe('19:10');
// });