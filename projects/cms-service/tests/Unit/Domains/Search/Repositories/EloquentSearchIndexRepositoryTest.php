<?php

use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexRepository;
use App\Domains\Search\Support\Indexing\IndexedDocument;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use App\Models\SearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EloquentSearchIndexRepository;
});

/**
 * مستند فهرس مبنيّ كما يبنيه SearchDocumentBuilder.
 *
 * الأعمدة المطويّة هي ما يُطابَق عليه فعلاً، فبناؤها هنا يجعل الاختبار
 * يمرّ بالعقد نفسه الذي يمرّ به مسار الفهرسة.
 */
function indexedDocument(array $overrides = []): IndexedDocument
{
    $title = $overrides['title'] ?? 'عنوان تجريبي';
    $content = $overrides['content'] ?? 'محتوى تجريبي';
    $meta = $overrides['meta'] ?? ['tags' => ['php', 'test']];

    $metaText = implode(' ', array_map(
        fn ($value) => is_array($value) ? implode(' ', $value) : (string) $value,
        $meta
    ));

    $titleFold = TextFolder::fold($title);
    $contentFold = TextFolder::fold($content);
    $metaFold = TextFolder::fold($metaText);

    return new IndexedDocument(
        entryId: 100,
        projectId: 1,
        language: 'ar',
        row: [
            'entry_id' => 100,
            'data_type_id' => 1,
            'project_id' => 1,
            'language' => 'ar',
            'data_type_slug' => 'articles',
            'title' => $title,
            'content' => $content,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'title_fold' => $titleFold,
            'content_fold' => $contentFold,
            'meta_fold' => $metaFold,
            'title_terms' => count(Segmenter::tokenize($titleFold)),
            'content_terms' => count(Segmenter::tokenize($contentFold)),
            'meta_terms' => count(Segmenter::tokenize($metaFold)),
            'status' => $overrides['status'] ?? 'published',
            'published_at' => now()->toDateTimeString(),
        ],
        attributes: [
            ['key' => 'tags', 'value_text' => 'php,test', 'value_num' => null],
        ],
    );
}

// 1. اختبار الـ Upsert (الحفظ والتحديث)
test('upsert creates new record and updates existing one', function () {
    $this->repository->upsert(indexedDocument());

    expect(SearchIndex::count())->toBe(1);

    $record = SearchIndex::first();

    expect($record->title)->toBe('عنوان تجريبي')
        ->and($record->meta)->toBe(['tags' => ['php', 'test']])
      // العمود الذي كان NULL فيُعطّل كل فلترة بنوع المحتوى
        ->and($record->data_type_slug)->toBe('articles');

    /*
     | الأعمدة المطويّة هي ما يُطابقه الفهرس النصّي، وقيم meta تدخلها —
     | فبدونها تبقى الحقول المخصَّصة خارج البحث كلياً.
     */
    expect($record->title_fold)->toBe(TextFolder::fold('عنوان تجريبي'))
        ->and($record->meta_fold)->toContain('php');

    // السمات البنيوية تُكتب مع الصفّ لا بعده
    expect(DB::table('search_index_attributes')->where('entry_id', 100)->count())->toBe(1);

    // اختبار التحديث (نفس الـ entry_id واللغة)
    $this->repository->upsert(indexedDocument([
        'title' => 'عنوان محدث',
        'content' => 'محتوى محدث',
        'status' => 'draft',
    ]));

    expect(SearchIndex::count())->toBe(1)
        ->and(SearchIndex::first()->title)->toBe('عنوان محدث')
      // الاستبدال لا التراكم: سمة واحدة تبقى واحدة
        ->and(DB::table('search_index_attributes')->where('entry_id', 100)->count())->toBe(1);
});

// 2. اختبار الحذف عبر الـ ID (يحذف كل اللغات)
test('deleteByEntryId removes all entries regardless of language', function () {
    // إضافة سجلين لنفس الـ entry_id بلغات مختلفة
    SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'ar']);
    SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'en']);

    $this->repository->deleteByEntryId(55);

    expect(SearchIndex::where('entry_id', 55)->count())->toBe(0);
});

// 3. اختبار الحذف الدقيق (Entry + Language)
test('deleteByEntryAndLanguage removes only specific entry', function () {
    SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'ar']);
    SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'en']);

    $this->repository->deleteByEntryAndLanguage(55, 'ar');

    expect(SearchIndex::where('entry_id', 55)->where('language', 'ar')->exists())->toBeFalse();
    expect(SearchIndex::where('entry_id', 55)->where('language', 'en')->exists())->toBeTrue();
});

// 4. اختبار التحقق من الوجود
test('existsForEntry returns correct boolean', function () {
    SearchIndex::factory()->create(['entry_id' => 99, 'language' => 'fr']);

    expect($this->repository->existsForEntry(99, 'fr'))->toBeTrue();
    expect($this->repository->existsForEntry(99, 'ar'))->toBeFalse();
});
