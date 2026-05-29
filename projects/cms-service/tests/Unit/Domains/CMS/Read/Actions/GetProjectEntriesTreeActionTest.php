<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetProjectEntriesTreeAction;
use App\Domains\CMS\Read\Repositories\EntryProjectReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    $this->projectRepo = mock(EntryProjectReadRepositoryInterface::class);
    $this->entryRepo = mock(EntryReadRepository::class);
    $this->relationRepo = mock(EntryRelationRepository::class);
    $this->langResolver = mock(LanguageResolver::class);

    $this->action = new GetProjectEntriesTreeAction(
        $this->projectRepo,
        $this->entryRepo,
        $this->relationRepo,
        $this->langResolver
    );
});

test('it builds a hierarchical tree correctly from flat data', function () {
    $projectId = 1;
    $filters = ['lang' => 'en'];

    // 1. التجهيز
    // المدخلات: شجرة بسيطة (Root -> Child -> Grandchild)
    $entries = [
        ['id' => 1, 'name' => 'Root'],
        ['id' => 2, 'name' => 'Child'],
        ['id' => 3, 'name' => 'Grandchild']
    ];

    $relations = [
        ['parent_id' => 1, 'child_id' => 2],
        ['parent_id' => 2, 'child_id' => 3]
    ];

    // Mocks Setup
    $this->langResolver->shouldReceive('resolve')->andReturn('en');
    $this->langResolver->shouldReceive('fallback')->andReturn('en');

    // محاكاة استرجاع الـ IDs
    $query = mock(Builder::class);
    $this->projectRepo->shouldReceive('queryByProject')->andReturn($query);
    $query->shouldReceive('get')->andReturn(collect([['id' => 1], ['id' => 2], ['id' => 3]]));

    // محاكاة استرجاع الـ Values
    $this->entryRepo->shouldReceive('findPublishedManyWithValues')->andReturn($entries);

    // محاكاة استرجاع العلاقات
    $this->relationRepo->shouldReceive('getAllByProject')->with($projectId)->andReturn($relations);

    // 2. التنفيذ
    $tree = $this->action->execute($projectId, $filters);

    // 3. التأكيد
    expect($tree)->toBeArray()->toHaveCount(1) // Root واحد فقط
        ->and($tree[0]['name'])->toBe('Root')
        ->and($tree[0]['children'])->toHaveCount(1) // لديه ابن واحد
        ->and($tree[0]['children'][0]['name'])->toBe('Child')
        ->and($tree[0]['children'][0]['children'][0]['name'])->toBe('Grandchild'); // الحفيد
});