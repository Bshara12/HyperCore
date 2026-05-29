<?php

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchRepository;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\SearchResultRanker;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
  $this->ranker = Mockery::mock(SearchResultRanker::class);
  $this->repository = new EloquentSearchRepository($this->ranker);

  // أضف هذا السطر لتعريف raw للـ DB Facade
  DB::shouldReceive('raw')->andReturnUsing(fn($expression) => $expression);
});

function invokePrivateMethod($object, string $methodName, array $parameters = [])
{
  $reflection = new ReflectionClass(get_class($object));
  $method = $reflection->getMethod($methodName);
  $method->setAccessible(true);
  return $method->invokeArgs($object, $parameters);
}

test('buildWhereFragment includes NOT LIKE when exclusions exist', function () {
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $booleanQuery = '+iphone -case'; // يحتوي على استثناء

    // استدعاء مباشر للدالة الخاصة
  /** @var SqlFragment $fragment */
  $fragment = invokePrivateMethod($this->repository, 'buildWhereFragment', [$dto, $booleanQuery, 'general', 0.0]);

  // نتحقق من محتوى الـ SQL
  expect($fragment->sql)->toContain("NOT LIKE")
    ->and($fragment->sql)->toContain("si.project_id = ?");
});

// 2. اختبار تصفية نوع البيانات (DataType Filter)
test('buildWhereFragment adds data_type_slug filter when provided in DTO', function () {
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10, dataTypeSlug: 'product');
  $processed = new ProcessedKeyword('iphone', '+iphone', ['iphone'], 'iphone', ['+iphone'], [], ['intent' => 'general', 'confidence' => 0], [], false);

  DB::shouldReceive('selectOne')
    ->once()
    ->with(
      Mockery::on(fn($sql) => str_contains($sql, 'si.data_type_slug = ?')),
      Mockery::any() // <-- تجاهل الـ bindings هنا للتركيز على الـ SQL
    )
    ->andReturn((object)['total' => 0]);

  $this->repository->search($dto, $processed, UserPreferenceDTO::noHistory());
});

// 3. اختبار التصفية بناءً على النية (Intent-based Filter)
test('buildWhereFragment applies intent filter when confidence is high', function () {
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  // نية 'product' مع ثقة 0.9 (أعلى من 0.3)
  $processed = new ProcessedKeyword('iphone', '+iphone', ['iphone'], 'iphone', ['+iphone'], [], ['intent' => 'product', 'confidence' => 0.9], [], false);

  DB::shouldReceive('selectOne')
    ->once()
    ->with(Mockery::on(function ($sql) {
      // نتحقق أن الاستعلام يحتوي على IN (لأن المنتجات تعود كـ مصفوفة)
      return str_contains($sql, 'si.data_type_slug IN');
    }), Mockery::any())
    ->andReturn((object)['total' => 0]);

  $this->repository->search($dto, $processed, UserPreferenceDTO::noHistory());
});

test('search captures correct metadata when success happens at relaxation step 1', function () {
  // 1. التحضير (Arrange)
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);

  // محاكاة وجود خطوتين للبحث (Step 0 و Step 1)
  $processed = new ProcessedKeyword(
    original: 'iphone',
    booleanQuery: '+iphone',
    cleanWords: ['iphone'],
    primaryWord: 'iphone',
    relaxedQueries: ['+iphone', '+iphone*'], // step 0 يمثل '+iphone' و step 1 يمثل '+iphone*'
    expandedGroups: [],
    intent: ['intent' => 'product', 'confidence' => 0.9],
    dbExpandedGroups: [],
    hadDbExpansion: false
  );
  $preference = UserPreferenceDTO::noHistory();

  // 2. محاكاة استجابة قاعدة البيانات (DB Sequence)
  // - الاستدعاء الأول (للـ Step 0) يجب أن يعيد total = 0 (فشل)
  // - الاستدعاء الثاني (للـ Step 1) يجب أن يعيد total = 5 (نجاح)
  DB::shouldReceive('selectOne')->twice()->andReturn(
    (object)['total' => 0],
    (object)['total' => 5]
  );

  // عندما ينجح الاستدعاء الثاني، سيتم استدعاء DB::select لجلب البيانات
  DB::shouldReceive('select')->once()->andReturn([
    (object)['entry_id' => 1, 'title' => 'iPhone 15']
  ]);

  // محاكاة الـ Ranker
  $this->ranker->shouldReceive('rerank')->once()->andReturn([
    ['entry_id' => 1, 'title' => 'iPhone 15']
  ]);

  // 3. التنفيذ (Act)
  $result = $this->repository->search($dto, $processed, $preference);

  // 4. التحقق (Assert)
  // هنا نختبر تحديداً السطور 40-42
  expect($result['relaxation_step'])->toBe(1)             // التأكد من أن الخطوة هي 1
    ->and($result['query_used'])->toBe('+iphone*')      // التأكد من أن الكلمة المستخدمة هي الثانية
    ->and($result['intent']['intent'])->toBe('product') // التأكد من دمج الـ intent
    ->and($result['total'])->toBe(5);                   // التأكد من أن النتائج وصلت
});
test('searchWithExclusions routes to searchExcludeOnly when cleanWords are empty', function () {
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  // نجعل cleanWords فارغة لإجبار الكود على دخول شرط searchExcludeOnly
  $processed = new ProcessedKeyword('', '', [], '', [], [], [], [], false);
  $preference = UserPreferenceDTO::noHistory();
  $excludeTerms = ['test'];

  // نتوقع أن يتم استدعاء selectOne (الذي يستخدمه searchExcludeOnly)
  DB::shouldReceive('selectOne')->once()->andReturn((object)['total' => 0]);

  // نقوم باستدعاء التابع مباشرة من الـ repository
  $result = $this->repository->searchWithExclusions($dto, $processed, $preference, $excludeTerms);

  expect($result)->toHaveKey('total', 0);
});

test('searchWithExclusions calls search directly when no exclusions provided', function () {
  // 1. تحضير الـ Repository كـ Partial Mock
  $repo = Mockery::mock(EloquentSearchRepository::class, [$this->ranker])->makePartial();

  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $processed = new ProcessedKeyword('iphone', '+iphone', ['iphone'], 'iphone', ['+iphone'], [], [], [], false);
  $preference = UserPreferenceDTO::noHistory();

  // 2. التوقعات: يجب أن يتم استدعاء search() مباشرة
  $repo->shouldReceive('search')
    ->once()
    ->with($dto, $processed, $preference)
    ->andReturn(['items' => [], 'total' => 0]);

  // 3. التنفيذ
  $repo->searchWithExclusions($dto, $processed, $preference, []);
});

test('searchWithExclusions injects exclusions suffix and calls search', function () {
  $repo = Mockery::mock(EloquentSearchRepository::class, [$this->ranker])->makePartial();

  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $processed = new ProcessedKeyword('iphone', '+iphone', ['iphone'], 'iphone', ['+iphone'], [], [], [], false);
  $preference = UserPreferenceDTO::noHistory();
  $excludeTerms = ['case', 'cover']; // مصطلحات للاستبعاد

  // 2. التوقعات: التحقق من أن search() تم استدعاؤها مع ProcessedKeyword معدل
  $repo->shouldReceive('search')
    ->once()
    ->with(
      $dto,
      Mockery::on(function ($p) {
        // التأكد من أن الـ booleanQuery يحتوي على الـ suffix المضاف
        return str_contains($p->booleanQuery, '-case') &&
          str_contains($p->booleanQuery, '-cover');
      }),
      $preference
    )
    ->andReturn(['items' => [], 'total' => 0]);

  // 3. التنفيذ
  $repo->searchWithExclusions($dto, $processed, $preference, $excludeTerms);
});

test('incrementClickCount executes correct query', function () {
  $entryId = 101;
  $language = 'ar';

  // Mock للسلسلة: table -> where -> where -> increment
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');
  DB::shouldReceive('table')->once()->with('search_indices')->andReturn($builder);

  $builder->shouldReceive('where')->with('entry_id', $entryId)->andReturnSelf();
  $builder->shouldReceive('where')->with('language', $language)->andReturnSelf();
  $builder->shouldReceive('increment')->once()->with('click_count');

  $this->repository->incrementClickCount($entryId, $language);
});

test('getUserKeywords calculates correct weights for recent searches', function () {
  // 1. إنشاء Mock للـ Query Builder
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');

  // 2. إعداد DB::table لتعيد الـ builder بدلاً من الـ facade
  DB::shouldReceive('table')->with('user_search_logs')->andReturn($builder);

  // 3. إعداد DB::raw (لأنها تُستخدم داخل select)
  DB::shouldReceive('raw')->andReturn('MAX(searched_at) as last_searched');

  // 4. إعداد الـ builder ليدعم السلسلة (Fluent Methods)
  $builder->shouldReceive('select')->andReturnSelf();
  $builder->shouldReceive('where')->andReturnSelf();
  $builder->shouldReceive('whereNotNull')->andReturnSelf();
  $builder->shouldReceive('whereRaw')->andReturnSelf();
  $builder->shouldReceive('groupBy')->andReturnSelf();
  $builder->shouldReceive('orderByDesc')->andReturnSelf();
  $builder->shouldReceive('limit')->andReturnSelf();

  // 5. في النهاية يجب أن يعيد الـ get البيانات
  $builder->shouldReceive('get')->once()->andReturn(collect([
    (object)['keyword' => 'iphone 15', 'last_searched' => now()]
  ]));

  // استدعاء التابع
  $reflection = new ReflectionClass($this->repository);
  $method = $reflection->getMethod('getUserKeywords');
  $method->setAccessible(true);

  $results = $method->invokeArgs($this->repository, [1, 1]); // userId, projectId

  expect($results)->toBeArray()
    ->toHaveCount(1)
    ->and($results[0]['word'])->toBe('iphone');
});

test('searchExcludeOnly returns empty array when no results found', function () {
  // 1. Arrange: إعداد المدخلات
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $excludeTerms = ['badword'];
  $preference = UserPreferenceDTO::noHistory();

  // 2. Mock: قاعدة البيانات تعيد 0 في الـ Count
  DB::shouldReceive('selectOne')->once()->andReturn((object)['total' => 0]);

  // 3. Act: استدعاء التابع الخاص
  $reflection = new ReflectionClass($this->repository);
  $method = $reflection->getMethod('searchExcludeOnly');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->repository, [$dto, $excludeTerms, $preference]);

  // 4. Assert: التأكد من النتيجة
  expect($result['items'])->toBeEmpty()
    ->and($result['total'])->toBe(0);
});

test('searchExcludeOnly fetches, ranks, and returns paginated items successfully', function () {
  // 1. Arrange
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $excludeTerms = ['badword'];
  $preference = UserPreferenceDTO::noHistory();

  // 2. Mock DB
  // أولاً: الـ Count
  DB::shouldReceive('selectOne')->once()->andReturn((object)['total' => 20]);

  // ثانياً: الـ Fetch (قاعدة البيانات تعيد صفين)
  DB::shouldReceive('select')->once()->andReturn([
    (object)['entry_id' => 1, 'title' => 'Phone 1'],
    (object)['entry_id' => 2, 'title' => 'Phone 2']
  ]);

  // 3. Mock Ranker: يقوم بإعادة البيانات كما هي
  $this->ranker->shouldReceive('rerank')
    ->once()
    ->andReturn([
      ['entry_id' => 1, 'title' => 'Phone 1'],
      ['entry_id' => 2, 'title' => 'Phone 2']
    ]);

  // 4. Act
  $reflection = new ReflectionClass($this->repository);
  $method = $reflection->getMethod('searchExcludeOnly');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->repository, [$dto, $excludeTerms, $preference]);

  // 5. Assert
  expect($result['total'])->toBe(20)
    ->and($result['items'])->toHaveCount(2)
    ->and($result['items'][0]['entry_id'])->toBe(1);
});

test('searchExcludeOnly returns items as empty array when count > 0 but fetch returns no rows', function () {
  // 1. Arrange: إعداد المدخلات
  $dto = new SearchQueryDTO('iphone', 1, 'ar', 1, 10);
  $excludeTerms = ['badword'];
  $preference = UserPreferenceDTO::noHistory();

  // 2. Mock: 
  // - الـ count يعيد 5 (لنتجاوز الشرط الأول total === 0)
  DB::shouldReceive('selectOne')->once()->andReturn((object)['total' => 5]);

  // - الـ select يعيد مصفوفة فارغة (وهنا سيتحقق الشرط if (empty($rows)))
  DB::shouldReceive('select')->once()->andReturn([]);

  // 3. Act: استدعاء التابع عبر Reflection
  $reflection = new ReflectionClass($this->repository);
  $method = $reflection->getMethod('searchExcludeOnly');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->repository, [$dto, $excludeTerms, $preference]);

  // 4. Assert: التأكد من أن التابع عاد بـ items فارغة مع الاحتفاظ بالـ total
  expect($result)->toBe([
    'items' => [],
    'total' => 5
  ]);
});

test('getUserKeywords returns cached results on second call and does not hit DB', function () {
    // استخدم أرقاماً فريدة جداً لا تظهر في أي اختبار آخر لضمان عدم وجود كاش مسبق
    $userId = 9999; 
    $projectId = 9999;

    $builder = Mockery::mock('Illuminate\Database\Query\Builder');
    
    // التوقع لا يزال once() لأنه يجب أن يتم استدعاؤها مرة واحدة فقط (في المرة الأولى)
    DB::shouldReceive('table')->once()->with('user_search_logs')->andReturn($builder);
    DB::shouldReceive('raw')->andReturn('MAX(searched_at) as last_searched');
    
    $builder->shouldReceive('select', 'where', 'whereNotNull', 'whereRaw', 'groupBy', 'orderByDesc', 'limit')
            ->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([
        (object)['keyword' => 'iphone', 'last_searched' => now()]
    ]));

    $reflection = new ReflectionClass($this->repository);
    $method = $reflection->getMethod('getUserKeywords');
    $method->setAccessible(true);

    // الاستدعاء الأول: سيصل للـ DB ويملأ الكاش
    $method->invokeArgs($this->repository, [$userId, $projectId]);

    // الاستدعاء الثاني: سيصل للكاش ولن يصل للـ DB
    // وبما أن Mockery يتوقع once()، فهو لن يشتكي لأن الاستدعاء الأول حقق الشرط
    $results = $method->invokeArgs($this->repository, [$userId, $projectId]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['word'])->toBe('iphone');
});

test('executeSearch returns items as empty array with total when fetchRows returns empty', function () {
    // 1. إنشاء الكائن الحقيقي مع تمرير كافة المتطلبات الإجبارية للـ Constructor
    $processed = new \App\Domains\Search\Support\ProcessedKeyword(
        original: 'iphone',
        booleanQuery: 'iphone',
        cleanWords: ['iphone'],
        primaryWord: 'iphone',
        relaxedQueries: [],
        expandedGroups: [],
        intent: ['intent' => 'general', 'confidence' => 0.9]
        // الباقي قيم افتراضية لا نحتاج لتمريرها
    );

    $dto = new \App\Domains\Search\DTOs\SearchQueryDTO('iphone', 1, 'ar', 1, 10);
    $preference = \App\Domains\Search\DTOs\UserPreferenceDTO::noHistory();
    $booleanQuery = 'iphone';

    // 2. إعداد الـ DB Mocks (تأكد أن هذه هي الطريقة التي تعتمدها في مشروعك)
    DB::shouldReceive('selectOne')->once()->andReturn((object)['total' => 5]);
    DB::shouldReceive('select')->once()->andReturn([]);

    // 3. Act: استدعاء التابع الخاص عبر Reflection
    $reflection = new ReflectionClass($this->repository);
    $method = $reflection->getMethod('executeSearch');
    $method->setAccessible(true);

    $result = $method->invokeArgs($this->repository, [$dto, $processed, $booleanQuery, $preference]);

    // 4. Assert
    expect($result)->toBe([
        'items' => [],
        'total' => 5
    ]);
});