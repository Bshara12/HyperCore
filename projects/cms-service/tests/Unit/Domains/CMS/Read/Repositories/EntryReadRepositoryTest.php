<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Models\Project;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EntryReadRepository();

  // 1. تزييف قرص Supabase لمنع استدعاءات سيرفر خارجية أثناء الاختبار
  Storage::fake('supabase');

  // 2. بناء الهيكل الأساسي للعلاقات لتجنب قيود قاعدة البيانات (Foreign Keys)
  $this->project = Project::factory()->create();
  $this->dataType = DataType::factory()->create([
    'project_id' => $this->project->id,
    'slug' => 'articles',
    'name' => 'Articles'
  ]);

  // حقل نصي عادي
  $this->textField = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'title',
    'type' => 'text'
  ]);

  // حقل ميديا (صورة)
  $this->imageField = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'thumbnail',
    'type' => 'image'
  ]);
});

test('it returns null if entry is not found or scheduled in the future', function () {
  // مدخل مجدول في المستقبل
  $futureEntryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'future-post',
    'status' => 'published',
    'scheduled_at' => now()->addDays(5),
  ]);

  $result = $this->repository->findPublishedWithValues($futureEntryId, 'ar', 'en');
  expect($result)->toBeNull();

  // معرف غير موجود أصلاً
  $notFoundResult = $this->repository->findPublishedWithValues(9999, 'ar', 'en');
  expect($notFoundResult)->toBeNull();
});

test('it fetches published entry with values and respects language fallback', function () {
  // 1. إنشاء الـ Entry
  $entryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'test-article',
    'status' => 'published',
    'scheduled_at' => null,
  ]);

  // 2. إدخال قيمتين لنفس الحقل بلغات مختلفة لفحص الـ Fallback (الطلب لـ 'ar' والبديل 'en')
  DB::table('data_entry_values')->insert([
    [
      'data_entry_id' => $entryId,
      'data_type_field_id' => $this->textField->id,
      'language' => 'en',
      'value' => 'English Title',
    ],
    [
      'data_entry_id' => $entryId,
      'data_type_field_id' => $this->textField->id,
      'language' => 'ar',
      'value' => 'العنوان العربي',
    ]
  ]);

  // التنفيذ وطلب اللغة العربية
  $result = $this->repository->findPublishedWithValues($entryId, 'ar', 'en');

  expect($result)->not->toBeNull()
    ->and($result['id'])->toBe($entryId)
    ->and($result['data_type_slug'])->toBe('articles')
    ->and($result['values']['title'])->toBe('العنوان العربي');
});

test('it processes media fields and generates correct urls', function () {
  $entryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'media-article',
    'status' => 'published',
  ]);

  // إدخال مسار ملف في جدول القيم
  $filePath = 'uploads/images/pic.png';
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entryId,
    'data_type_field_id' => $this->imageField->id,
    'language' => '0',
    'value' => $filePath,
  ]);

  $result = $this->repository->findPublishedWithValues($entryId, 'ar', 'en');

  expect($result['values']['thumbnail'])->toBeArray()->toHaveCount(1);

  $mediaItem = $result['values']['thumbnail'][0];
  expect($mediaItem['name'])->toBe('pic.png')
    ->and($mediaItem['extension'])->toBe('png')
    ->and($mediaItem['url'])->toContain('/storage/uploads/images/pic.png');
});

test('it attaches seo data with correct language priority', function () {
  $entryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'seo-article',
    'status' => 'published',
  ]);

  // تم الاكتفاء بالأعمدة الضامنين لوجودها (data_entry_id و language) لتجنب اختلاف أسماء الأعمدة الأخرى
  DB::table('seo_entries')->insert([
    [
      'data_entry_id' => $entryId,
      'language' => 'en',
    ],
    [
      'data_entry_id' => $entryId,
      'language' => 'ar',
    ]
  ]);

  // عند طلب 'ar' يجب أن يعود السجل العربي أولاً بسبب شرط الـ OrderByRaw المحدد بالكود الخاص بك
  $result = $this->repository->findPublishedWithValues($entryId, 'ar', 'en');

  expect($result['seo'])->toBeArray()
    ->and($result['seo']['language'])->toBe('ar'); // التحقق من جلب اللغة المطلوبة بنجاح
});

test('it skips media items with empty values', function () {
  // 1. إنشاء Entry جديد
  $entryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'empty-media-article',
    'status' => 'published',
  ]);

  // 2. إدخال سجلين لنفس حقل الميديا: واحد بقيمة صحيحة والآخر بقيمة فارغة
  DB::table('data_entry_values')->insert([
    [
      'data_entry_id' => $entryId,
      'data_type_field_id' => $this->imageField->id,
      'language' => 'ar',
      'value' => 'uploads/valid-image.png', // قيمة صالحة
    ],
    [
      'data_entry_id' => $entryId,
      'data_type_field_id' => $this->imageField->id,
      'language' => 'en',
      'value' => '', // قيمة فارغة لتشغيل الـ continue في السطر 92
    ]
  ]);

  $result = $this->repository->findPublishedWithValues($entryId, 'ar', 'en');

  // التأكيد: يجب أن تحتوي المصفوفة على عنصر واحد فقط (الصورة الصالحة) وتتجاهل الفارغة
  expect($result['values']['thumbnail'])->toBeArray()->toHaveCount(1)
    ->and($result['values']['thumbnail'][0]['name'])->toBe('valid-image.png');
});

### 1. اختبار التحقق المبكر (Early Returns)

test('findPublishedManyWithValues returns empty array when given no IDs', function () {
  // تمرير مصفوفة فارغة لتشغيل أول شرط في الدالة
  $result = $this->repository->findPublishedManyWithValues([], 'ar', 'en');

  expect($result)->toBeEmpty();
});

test('findPublishedManyWithValues returns empty array if no entries match or are scheduled in future', function () {
  // إنشاء سجل مجدول في المستقبل (لن يتم جلبها)
  $futureEntryId = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'future-post',
    'status' => 'published',
    'scheduled_at' => now()->addDays(5),
  ]);

  // تمرير معرف غير موجود ومعرف في المستقبل لتشغيل شرط $entries->isEmpty()
  $result = $this->repository->findPublishedManyWithValues([$futureEntryId, 9999], 'ar', 'en');

  expect($result)->toBeEmpty();
});

### 2. اختبار الجلب الجماعي الناجح ودعم اللغات الـ Fallback

test('findPublishedManyWithValues fetches multiple entries with correct values and fallbacks', function () {
  // إنشاء سجلين صالحين للبث الجماعي
  $entry1 = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'first-post',
    'status' => 'published',
  ]);

  $entry2 = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'second-post',
    'status' => 'published',
  ]);

  // إدخال القيم لـ السجلين (الأول يدعم العربية، الثاني يسقط على الإنجليزية كـ Fallback)
  DB::table('data_entry_values')->insert([
    [
      'data_entry_id' => $entry1,
      'data_type_field_id' => $this->textField->id,
      'language' => 'ar',
      'value' => 'المقال الأول باللغة العربية',
    ],
    [
      'data_entry_id' => $entry2,
      'data_type_field_id' => $this->textField->id,
      'language' => 'en',
      'value' => 'Second Post in English',
    ]
  ]);

  $result = $this->repository->findPublishedManyWithValues([$entry1, $entry2], 'ar', 'en');

  expect($result)->toHaveCount(2);

  $mapped1 = collect($result)->firstWhere('id', $entry1);
  $mapped2 = collect($result)->firstWhere('id', $entry2);

  // التأكد من جلب البيانات وهيكل المصفوفة المطلوب تماماً
  expect($mapped1['values']['title'])->toBe('المقال الأول باللغة العربية')
    ->and($mapped1['data_type_slug'])->toBe('articles')
    ->and($mapped1['project_id'])->toBe($this->project->id);

  expect($mapped2['values']['title'])->toBe('Second Post in English');
});

### 3. اختبار معالجة الميديا (عنصر واحد مقابل عناصر متعددة وتخطي الفارغ)

test('findPublishedManyWithValues handles single vs multiple media items and skips empty assets', function () {
  // 1. سجل للميديا المفردة مع عنصر فارغ لتشغيل شرط (! $item->value)
  $singleMediaEntry = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'single-media-post',
    'status' => 'published',
  ]);

  DB::table('data_entry_values')->insert([
    [
      'data_entry_id' => $singleMediaEntry,
      'data_type_field_id' => $this->imageField->id,
      'language' => '0',
      'value' => 'uploads/single-logo.png',
    ],
    [
      'data_entry_id' => $singleMediaEntry,
      'data_type_field_id' => $this->imageField->id,
      'language' => 'ar',
      'value' => '', // قيمة فارغة لتشغيل الـ continue السطر 92 في دالتك
    ]
  ]);

  // 2. سجل يحتوي على ميديا متعددة (لتشغيل شرط count($mediaItems) > 1)
  $multiMediaEntry = DB::table('data_entries')->insertGetId([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'multi-media-post',
    'status' => 'published',
  ]);

  DB::table('data_entry_values')->insert([
    [
      'data_entry_id' => $multiMediaEntry,
      'data_type_field_id' => $this->imageField->id,
      'language' => 'ar',
      'value' => 'uploads/gallery-1.png',
    ],
    [
      'data_entry_id' => $multiMediaEntry,
      'data_type_field_id' => $this->imageField->id,
      'language' => 'en',
      'value' => 'uploads/gallery-2.png',
    ]
  ]);

  // التنفيذ
  $result = $this->repository->findPublishedManyWithValues([$singleMediaEntry, $multiMediaEntry], 'ar', 'en');

  $resSingle = collect($result)->firstWhere('id', $singleMediaEntry);
  $resMulti = collect($result)->firstWhere('id', $multiMediaEntry);

  // التأكيد على الميديا المفردة (يجب أن تعود كمصفوفة بخصائص الملف مباشرة دون مصفوفة بداخل مصفوفة)
  expect($resSingle['values']['thumbnail'])->toBeArray()
    ->toHaveKey('name', 'single-logo.png')
    ->not->toHaveKey(0); // ليست مصفوفة متعددة لأن العنصر الثاني الفارغ تم تخطيه

  // التأكيد على الميديا المتعددة (يجب أن تعود كمصفوفة من المصفوفات لتخطيها شرط count > 1)
  expect($resMulti['values']['thumbnail'])->toBeArray()->toHaveCount(2)
    ->and($resMulti['values']['thumbnail'][0]['name'])->toBe('gallery-1.png')
    ->and($resMulti['values']['thumbnail'][1]['name'])->toBe('gallery-2.png');
});
