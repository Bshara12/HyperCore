<?php

use App\Domains\CMS\Services\Rate\RatingService;
use App\Domains\CMS\Actions\Rate\RateAction;
use App\Domains\CMS\Actions\Rate\GetRatingsAction;
use App\Domains\CMS\Actions\Rate\GetRatingStatsAction;
use App\Domains\CMS\DTOs\Rate\RateDTO;
use App\Domains\CMS\DTOs\Rate\GetRatingsDTO;
use App\Domains\CMS\DTOs\Rate\GetRatingStatsDTO;

beforeEach(function () {
  $this->rateAction = Mockery::mock(RateAction::class);
  $this->getRatingsAction = Mockery::mock(GetRatingsAction::class);
  $this->statsAction = Mockery::mock(GetRatingStatsAction::class);

  $this->service = new RatingService(
    $this->rateAction,
    $this->getRatingsAction,
    $this->statsAction
  );
});

test('it delegates rating to RateAction', function () {
  $dto = new RateDTO(1, 'Product', 100, 5, 'Great!');

  $this->rateAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(true);

  $result = $this->service->rate($dto);

  expect($result)->toBeTrue();
});

test('it delegates fetching ratings to GetRatingsAction', function () {
  $dto = new GetRatingsDTO('Product', 100);
  $expected = ['rating1', 'rating2'];

  $this->getRatingsAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn($expected);

  $result = $this->service->getRatings($dto);

  expect($result)->toBe($expected);
});

test('it delegates stats to GetRatingStatsAction', function () {
  $dto = new GetRatingStatsDTO('Product', 100);
  $expected = ['average' => 4.5, 'count' => 10];

  $this->statsAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn($expected);

  $result = $this->service->getStats($dto);

  expect($result)->toBe($expected);
});
