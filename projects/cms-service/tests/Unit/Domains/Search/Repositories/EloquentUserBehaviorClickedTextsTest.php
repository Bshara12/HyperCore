<?php

use App\Domains\Search\Repositories\Eloquent\EloquentUserBehaviorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const PROJECT = 1;
const USER = 7;

beforeEach(function () {
    $this->repository = new EloquentUserBehaviorRepository();
});

function seedIndexRow(int $entryId, string $language, string $title, ?string $content = null): void
{
    DB::table('search_indices')->insert([
        'entry_id' => $entryId,
        'data_type_id' => 1,
        'project_id' => PROJECT,
        'language' => $language,
        'title' => $title,
        'content' => $content,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedSearchLog(string $keyword, string $language): int
{
    return (int) DB::table('user_search_logs')->insertGetId([
        'user_id' => USER,
        'project_id' => PROJECT,
        'keyword' => $keyword,
        'language' => $language,
        'results_count' => 3,
        'searched_at' => now(),
    ]);
}

function seedClick(int $entryId, ?int $searchLogId = null): void
{
    DB::table('user_click_logs')->insert([
        'user_id' => USER,
        'project_id' => PROJECT,
        'search_log_id' => $searchLogId,
        'entry_id' => $entryId,
        'data_type_id' => 1,
        'result_position' => 1,
        'clicked_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────

test('النقرة الواحدة تُنتج نصاً واحداً لا نصاً لكل لغة مفهرسة', function () {
    seedIndexRow(10, 'ar', 'آيفون 15 برو ماكس');
    seedIndexRow(10, 'en', 'iPhone 15 Pro Max');

    seedClick(entryId: 10, searchLogId: seedSearchLog('ايفون', 'ar'));

    $texts = $this->repository->getClickedEntryTexts(PROJECT, USER);

    // قبل الإصلاح: صفّان → المصطلح يُحتسب مرتين من نقرة واحدة فيتجاوز
    // MIN_TERM_SIGNAL، مع تسرّب مفردات اللغة الأخرى.
    expect($texts)->toHaveCount(1)
        ->and($texts[0])->toBe('آيفون 15 برو ماكس');
});

test('لا نتعلّم مفردات لغة أخرى من بحث بالعربية', function () {
    seedIndexRow(11, 'ar', 'سامسونج جالكسي');
    seedIndexRow(11, 'en', 'Samsung Galaxy Ultra');

    seedClick(entryId: 11, searchLogId: seedSearchLog('سامسونج', 'ar'));

    $texts = $this->repository->getClickedEntryTexts(PROJECT, USER);

    expect($texts)->toHaveCount(1)
        ->and($texts[0])->not->toContain('Ultra');
});

test('النقر المباشر بلا سجل بحث يُقبل ويُلتقط صف واحد فقط', function () {
    seedIndexRow(12, 'ar', 'ايباد برو');
    seedIndexRow(12, 'en', 'iPad Pro');

    seedClick(entryId: 12, searchLogId: null);

    $texts = $this->repository->getClickedEntryTexts(PROJECT, USER);

    expect($texts)->toHaveCount(1);
});

test('النص يجمع العنوان والمحتوى ويتجاهل الفارغ', function () {
    seedIndexRow(13, 'ar', 'ماكبوك برو', 'شريحة M3 Pro للمطورين');

    seedClick(entryId: 13, searchLogId: seedSearchLog('ماكبوك', 'ar'));

    expect($this->repository->getClickedEntryTexts(PROJECT, USER)[0])
        ->toBe('ماكبوك برو شريحة M3 Pro للمطورين');
});

test('نقرات الجلسة تُقرأ بنفس المنطق', function () {
    seedIndexRow(14, 'ar', 'ساعة ابل');
    seedIndexRow(14, 'en', 'Apple Watch');

    DB::table('user_click_logs')->insert([
        'user_id' => null,
        'project_id' => PROJECT,
        'search_log_id' => null,
        'entry_id' => 14,
        'data_type_id' => 1,
        'result_position' => 1,
        'session_id' => 'sess-abc',
        'clicked_at' => now(),
    ]);

    $texts = $this->repository->getClickedEntryTextsForSession(PROJECT, 'sess-abc');

    expect($texts)->toHaveCount(1);
});

test('النقرات الأقدم من نافذة التحليل تُستثنى', function () {
    seedIndexRow(15, 'ar', 'شاحن سريع');

    DB::table('user_click_logs')->insert([
        'user_id' => USER,
        'project_id' => PROJECT,
        'entry_id' => 15,
        'data_type_id' => 1,
        'result_position' => 1,
        'clicked_at' => now()->subDays(45),
    ]);

    expect($this->repository->getClickedEntryTexts(PROJECT, USER, days: 30))->toBe([]);
});

test('الحد الأقصى يُحتسب بعدد النقرات الفريدة لا صفوف الـ JOIN', function () {
    foreach ([20, 21, 22] as $entryId) {
        seedIndexRow($entryId, 'ar', "منتج {$entryId}");
        seedIndexRow($entryId, 'en', "Product {$entryId}");
        seedClick($entryId, seedSearchLog('منتج', 'ar'));
    }

    expect($this->repository->getClickedEntryTexts(PROJECT, USER, limit: 2))->toHaveCount(2);
});
