<?php

namespace Tests\Feature\Domains\E_Commerce\Actions\Pricing;

use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Services\CMS\CMSApiClient;
use Mockery;

it('fetches entries by ids from the cms api client', function () {
  $entryIds = [1, 2, 3];
  $mockedResponse = [
    ['id' => 1, 'slug' => 'product-1', 'values' => ['price' => 100]],
  ];

  $cmsClientMock = Mockery::mock(CMSApiClient::class);
  $cmsClientMock
    ->shouldReceive('getEntriesByIds')
    ->once()
    ->with($entryIds)
    ->andReturn($mockedResponse);

  $action = new FetchEntriesByIdsAction($cmsClientMock);
  $result = $action->execute($entryIds);

  expect($result)->toBeArray()->toHaveCount(1);
});
