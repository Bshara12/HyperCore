<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\RatingController;
use App\Domains\CMS\Services\Rate\RatingService;
use App\Domains\CMS\Requests\RateRequest;
use App\Domains\CMS\Requests\GetRatingsRequest;
use App\Domains\CMS\Requests\GetRatingStatsRequest;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\ParameterBag;

class RatingControllerTest extends TestCase
{
  private $ratingServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->ratingServiceMock = Mockery::mock(RatingService::class);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_stores_a_rating()
  {
    // 1. حل مشكلة attributes
    $request = Mockery::mock(RateRequest::class);
    $request->attributes = new ParameterBag(['auth_user' => ['id' => 1]]);

    // محاكاة الخصائص التي يقرأها الـ DTO
    $request->rateable_type = 'project';
    $request->rateable_id = 1;
    $request->rating = 5;
    $request->review = 'Excellent!';

    $this->ratingServiceMock->shouldReceive('rate')->once();

    $controller = new RatingController($this->ratingServiceMock);
    $response = $controller->store($request);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_ratings_list()
  {
    $request = Mockery::mock(GetRatingsRequest::class);
    $request->rateable_type = 'project';
    $request->rateable_id = 1;
    $request->shouldReceive('get')->with('per_page', 10)->andReturn(10);

    // إرجاع مجموعة (Collection) حقيقية
    $this->ratingServiceMock->shouldReceive('getRatings')->once()->andReturn(collect([]));

    $controller = new RatingController($this->ratingServiceMock);

    // 1. استدعاء الدالة
    $resource = $controller->index($request);

    // 2. تحويل الـ Resource إلى Response حقيقي باستخدام toResponse()
    $response = $resource->toResponse(app('request'));

    // 3. الآن يمكنك التحقق من كود الحالة
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_rating_stats()
  {
    $request = Mockery::mock(GetRatingStatsRequest::class);
    $request->rateable_type = 'project';
    $request->rateable_id = 1;

    $statsData = ['average' => 4.5, 'count' => 10];
    $this->ratingServiceMock->shouldReceive('getStats')->once()->andReturn($statsData);

    $controller = new RatingController($this->ratingServiceMock);
    $response = $controller->stats($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($statsData, $response->getData(true)['data']);
  }
}
