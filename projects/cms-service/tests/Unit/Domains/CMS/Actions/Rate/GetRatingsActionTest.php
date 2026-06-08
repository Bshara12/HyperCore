<?php

namespace Tests\Unit\Domains\CMS\Actions\Rate;

use App\Domains\Auth\Service\AuthServiceClient;
use App\Domains\CMS\Actions\Rate\GetRatingsAction;
use App\Domains\CMS\DTOs\Rate\GetRatingsDTO;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;

afterEach(function () {
    Mockery::close();
});

// 1. اختبار حالة الـ Early Return (السطر 32-34)
test('it returns ratings immediately when user_ids are empty', function () {
    $dto = new GetRatingsDTO('project', 1, 10);
    
    // بيانات لا تحتوي على user_id
    $items = [
        (object) ['id' => 1, 'user_id' => null],
        (object) ['id' => 2, 'user_id' => null]
    ];
    $paginator = new LengthAwarePaginator($items, count($items), 10);

    $repoMock = Mockery::mock(RatingRepositoryInterface::class);
    $repoMock->shouldReceive('paginateByRateable')->once()->andReturn($paginator);

    $authMock = Mockery::mock(AuthServiceClient::class);
    // نتأكد أن الخدمة لن تُستدعى أبداً
    $authMock->shouldNotReceive('getUsersByIds');

    $action = new GetRatingsAction($repoMock, $authMock);
    $result = $action->execute($dto);

    expect($result)->toBe($paginator);
});

// 2. اختبار حالة الربط الناجح (السطر 37-45)
test('it attaches user data when user_ids are present', function () {
    $dto = new GetRatingsDTO('project', 1, 10);
    
    // بيانات تحتوي على user_id
    $items = [
        (object) ['id' => 10, 'user_id' => 100],
        (object) ['id' => 20, 'user_id' => 200]
    ];
    $paginator = new LengthAwarePaginator($items, count($items), 10);

    $repoMock = Mockery::mock(RatingRepositoryInterface::class);
    $repoMock->shouldReceive('paginateByRateable')->once()->andReturn($paginator);

    $authMock = Mockery::mock(AuthServiceClient::class);
    // نتأكد أن الخدمة تُستدعى بالـ IDs الصحيحة
    $authMock->shouldReceive('getUsersByIds')
        ->once()
        ->with([100, 200])
        ->andReturn([
            ['id' => 100, 'name' => 'User A'],
            ['id' => 200, 'name' => 'User B']
        ]);

    $action = new GetRatingsAction($repoMock, $authMock);
    $result = $action->execute($dto);

    // التحقق من أن البيانات تم ربطها
    expect($result->items()[0]->user['name'])->toBe('User A')
        ->and($result->items()[1]->user['name'])->toBe('User B');
});