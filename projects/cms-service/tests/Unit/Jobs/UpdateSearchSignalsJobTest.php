<?php

namespace Tests\Unit\Jobs;

use App\Jobs\UpdateSearchSignalsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function insertSearchIndex(array $data): void
{
    DB::table('search_indices')->insert(array_merge([
        'entry_id' => 1,
        'data_type_id' => 1,
        'project_id' => 1,
        'click_count' => 0,
        'view_count' => 0,
        'published_at' => now()->toDateTimeString(),
        'ctr_score' => 0.0,
        'freshness_score' => 0.0,
        'popularity_score' => 0.0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $data));
}

beforeEach(function () {
    // نصف عمر ثابت كي لا يتبدّل المتوقَّع بتبدّل الضبط.
    config(['search.ranking.freshness_half_life_days' => 45.0]);
});

test('it correctly calculates all search signals for a freshly published entry', function () {
    insertSearchIndex([
        'id' => 1,
        'click_count' => 9,
        'view_count' => 19,
        'published_at' => Carbon::now()->toDateTimeString(),
    ]);

    (new UpdateSearchSignalsJob)->handle();

    $result = DB::table('search_indices')->where('id', 1)->first();

    // CTR = 9 / (19 + 1) = 0.45
    expect($result->ctr_score)->toEqual(0.45);

    // محتوى نُشر للتوّ: 2^(-0/45) = 1.0
    expect($result->freshness_score)->toEqual(1.0);

    // log10(10)*0.6 + log10(20)*0.3 + 1.0*0.1
    $expected = round((log10(10) * 0.6) + (log10(20) * 0.3) + 0.1, 4);
    expect($result->popularity_score)->toEqual($expected);
});

test('it applies gradual exponential decay rather than collapsing', function () {
    /*
     | الصيغة السابقة 1/(days+1) كانت تُبقي 0.125 فقط بعد أسبوع — أي
     | أن محتوى عمره سبعة أيام يفقد 88% من أفضلية حداثته، فيتحوّل
     | البحث فعلياً إلى ترتيب زمني.
     |
     | الانحلال الأسّي بنصف عمر 45 يوماً يُبقي نحو 0.90 بعد أسبوع.
     */
    insertSearchIndex([
        'id' => 2,
        'published_at' => Carbon::now()->subDays(7)->toDateTimeString(),
    ]);

    (new UpdateSearchSignalsJob)->handle();

    $result = DB::table('search_indices')->where('id', 2)->first();

    expect((float) $result->freshness_score)
        ->toBeGreaterThan(0.85)
        ->toBeLessThan(0.95);
});

test('it halves the freshness signal after exactly one half-life', function () {
    insertSearchIndex([
        'id' => 3,
        'published_at' => Carbon::now()->subDays(45)->toDateTimeString(),
    ]);

    (new UpdateSearchSignalsJob)->handle();

    $result = DB::table('search_indices')->where('id', 3)->first();

    expect((float) $result->freshness_score)->toEqualWithDelta(0.5, 0.01);
});

test('it gives no freshness credit to entries without a publish date', function () {
    /*
     | تاريخ نشر مفقود ليس دليلاً على حداثة. منحه قيمة افتراضية —
     | كما كان يفعل "يُعامَل كثلاثين يوماً" — يعني ترجيح محتوى لا
     | نعرف عمره على محتوى نعرف أنه أقدم منه بيوم واحد.
     */
    insertSearchIndex([
        'id' => 4,
        'published_at' => null,
    ]);

    (new UpdateSearchSignalsJob)->handle();

    expect((float) DB::table('search_indices')->where('id', 4)->first()->freshness_score)
        ->toEqual(0.0);
});

test('it executes raw sql statements when the driver is mysql', function () {
    Log::spy();

    $connection = \Mockery::mock();
    $connection->shouldReceive('getDriverName')->andReturn('mysql');

    DB::shouldReceive('connection')->andReturn($connection);

    // عبارتان: الأولى للـ CTR والحداثة، والثانية للشعبية التي تعتمد عليها.
    DB::shouldReceive('statement')->times(2);

    (new UpdateSearchSignalsJob)->handle();

    expect(true)->toBeTrue();
});

test('it logs error correctly when job fails from queue tier', function () {
    Log::spy();

    (new UpdateSearchSignalsJob)->failed(new \Exception('Queue processing timeout error'));

    Log::shouldHaveReceived('error')->once()->with(
        'UpdateSearchSignalsJob: failed',
        \Mockery::on(fn ($args) => $args['error'] === 'Queue processing timeout error')
    );
});
