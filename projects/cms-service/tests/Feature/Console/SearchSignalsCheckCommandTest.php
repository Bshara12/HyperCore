<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it outputs healthy stats and top entries without warnings', function () {
  // إدخال بيانات مثالية لا تفعل أي تحذيرات
  // تمت إضافة data_type_id و entry_id كأرقام لتطابق الـ Migration الحقيقي
  DB::table('search_indices')->insert([
    'project_id' => 1,
    'entry_id' => 1,
    'data_type_id' => 1,
    'title' => 'Healthy Entry',
    'click_count' => 50,
    'view_count' => 100,
    'ctr_score' => 0.5,
    'popularity_score' => 4.5,
    'freshness_score' => 2.0,
  ]);

  $this->artisan('search:check-signals --project=1')
    ->expectsTable(['Metric', 'Value'], [
      ['Total Rows', '1'],
      ['Total Clicks', '50'],
      ['Total Views', '100'],
      ['Avg CTR Score', 0.5],
      ['Avg Popularity', 4.5],
      ['Avg Freshness', 2],
      ['Rows w/ 0 Views', '0'],
      ['Rows w/ 0 Clicks', '0'],
    ])
    ->expectsOutput("\nTop 5 by click_count:")
    ->expectsTable(['Entry', 'Title', 'Clicks', 'Views', 'CTR', 'Popularity'], [
      [1, 'Healthy Entry', 50, 100, 0.5, 4.5]
    ])
    ->assertExitCode(0);
});

test('it warns when view_count is always zero', function () {
  // إدخال بيانات بصفر مشاهدات لتفعيل التحذير الأول
  DB::table('search_indices')->insert([
    'project_id' => 1,
    'entry_id' => 2,
    'data_type_id' => 1,
    'title' => 'Zero Views Entry',
    'click_count' => 0,
    'view_count' => 0, // هذا السطر يفعل التحذير
    'ctr_score' => 0,
    'popularity_score' => 0,
    'freshness_score' => 0,
  ]);

  $this->artisan('search:check-signals --project=1')
    ->expectsOutput('⚠ view_count is ALWAYS 0 - IncrementViewCountJob is not being dispatched!')
    ->assertExitCode(0);
});

test('it warns when ctr_score is zero despite having clicks', function () {
  // إدخال بيانات بمشاهدات ونقرات، لكن CTR صفر لتفعيل التحذير الثاني
  DB::table('search_indices')->insert([
    'project_id' => 1,
    'entry_id' => 3,
    'data_type_id' => 1,
    'title' => 'Zero CTR Entry',
    'click_count' => 10, // نقرات موجودة
    'view_count' => 100, // مشاهدات موجودة (لتجنب التحذير الأول)
    'ctr_score' => 0,    // معدل النقر صفر (هذا يفعل التحذير الثاني)
    'popularity_score' => 1,
    'freshness_score' => 1,
  ]);

  $this->artisan('search:check-signals --project=1')
    ->expectsOutput('⚠ ctr_score is 0 despite having clicks - Run UpdateSearchSignalsJob')
    ->assertExitCode(0);
});
