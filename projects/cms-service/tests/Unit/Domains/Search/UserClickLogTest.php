<?php

use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Models\UserSearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

// uses(RefreshDatabase::class);

// test('it belongs to search log', function () {
//   // 1. إنشاء سجل البحث يدويًا
//   $searchLog = UserSearchLog::create([
//     'user_id'           => 1,
//     'project_id'        => 1,
//     'keyword'           => 'test',
//     'language'          => 'ar',
//     'session_id'        => 'session_123',
//     'searched_at'       => now(),
//   ]);

//   // 2. إنشاء سجل النقرة يدويًا وربطه بمعرف البحث الحقيقي
//   $clickLog = UserClickLog::create([
//     'user_id'           => 1,
//     'project_id'        => 1,
//     'search_log_id'     => $searchLog->id,
//     'entry_id'          => 1,
//     'data_type_id'      => 1,
//     'result_position'   => 1,
//     'session_id'        => 'session_123',
//     'clicked_at'        => now(),
//   ]);

//   // 3. اختبار صحة العلاقة العكسية BelongsTo
//   expect($clickLog->searchLog)->toBeInstanceOf(UserSearchLog::class)
//     ->and($clickLog->searchLog->id)->toBe($searchLog->id);
// });

// test('it casts clicked_at to datetime', function () {
//   // 🔥 الحل: إنشاء سجل بحث حقيقي أولاً لإرضاء قيود قاعدة البيانات (Foreign Key)
//   $searchLog = UserSearchLog::create([
//     'user_id'           => 1,
//     'project_id'        => 1,
//     'keyword'           => 'test-cast',
//     'language'          => 'ar',
//     'session_id'        => 'session_cast_123',
//     'searched_at'       => now(),
//   ]);

//   // الآن نمرر المعرف الحقيقي $searchLog->id بدلاً من الرقم 1 الوهمي
//   $clickLog = UserClickLog::create([
//     'user_id'           => 1,
//     'project_id'        => 1,
//     'search_log_id'     => $searchLog->id,
//     'entry_id'          => 1,
//     'data_type_id'      => 1,
//     'result_position'   => 1,
//     'session_id'        => 'session_cast_123',
//     'clicked_at'        => '2026-05-24 19:00:00',
//   ]);

//   expect($clickLog->clicked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
//     ->and($clickLog->clicked_at->format('Y-m-d'))->toBe('2026-05-24');
// });
