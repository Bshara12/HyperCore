<?php

namespace Tests\Unit\CMS\Services;

use Tests\TestCase;
use Mockery;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use App\Events\EntryChanged;
use App\Domains\CMS\Services\DataEntryService;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Domains\CMS\DTOs\Data\CreateDataEntryDto;
use App\Domains\CMS\DTOs\Data\UpdateEntryDTO;
use App\Models\DataEntry;
use PHPUnit\Framework\Attributes\Test;

class DataEntryServiceTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  private function makeService($overrides = [])
  {
    $defaults = [
      'entries' => Mockery::mock('App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface'),
      'values' => Mockery::mock('App\Domains\CMS\Repositories\Interface\DataEntryValueRepository'),
      'seo' => Mockery::mock('App\Domains\CMS\Repositories\Interface\SeoEntryRepository'),
      'seoGenerator' => Mockery::mock('App\Domains\CMS\Services\SeoGeneratorService'),
      'stateResolver' => Mockery::mock('App\Domains\CMS\States\DataEntryStateResolver'),
      'relations' => Mockery::mock('App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository'),
      'fieldsRepo' => Mockery::mock('App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface'),
      'validatorResolver' => Mockery::mock('App\Domains\CMS\StrategyCheck\FieldValidatorResolver'),
      'mergeFiles' => Mockery::mock('App\Domains\CMS\Actions\Data\MergeFilesAction'),
      'normalizeScheduledAt' => Mockery::mock('App\Domains\CMS\Actions\Data\NormalizeScheduledAtAction'),
      'validateFields' => Mockery::mock('App\Domains\CMS\Actions\Data\ValidateFieldsAction'),
      'handleSeo' => Mockery::mock('App\Domains\CMS\Actions\Data\HandleSeoAction'),
      'insertValues' => Mockery::mock('App\Domains\CMS\Actions\Data\InsertValuesAction'),
      'deleteValues' => Mockery::mock('App\Domains\CMS\Actions\Data\DeleteValuesAction'),
      'handleRelations' => Mockery::mock('App\Domains\CMS\Actions\Data\HandleRelationsAction'),
      'resolveState' => Mockery::mock('App\Domains\CMS\Actions\Data\ResolveStateAction'),
      'deleteEntry' => Mockery::mock('App\Domains\CMS\Actions\Data\DeleteDataEntryAction'),
      'createAction' => Mockery::mock('App\Domains\CMS\Actions\Data\CreateDataEntryAction'),
    ];

    $deps = array_merge($defaults, $overrides);

    return new DataEntryService(
      $deps['entries'],
      $deps['values'],
      $deps['seo'],
      $deps['seoGenerator'],
      $deps['stateResolver'],
      $deps['relations'],
      $deps['fieldsRepo'],
      $deps['validatorResolver'],
      $deps['mergeFiles'],
      $deps['normalizeScheduledAt'],
      $deps['validateFields'],
      $deps['handleSeo'],
      $deps['insertValues'],
      $deps['deleteValues'],
      $deps['handleRelations'],
      $deps['resolveState'],
      $deps['deleteEntry'],
      $deps['createAction'],
    );
  }

  #[Test]

  public function it_calls_create_action()
  {
    $dto = Mockery::mock(CreateDataEntryDto::class);
    $createAction = Mockery::mock('App\Domains\CMS\Actions\Data\CreateDataEntryAction');

    $createAction->shouldReceive('execute')
      ->once()
      ->with(1, 2, $dto, 99)
      ->andReturn('created');

    $service = $this->makeService(['createAction' => $createAction]);

    $result = $service->create(1, 2, $dto, 99);

    $this->assertEquals('created', $result);
  }

  #[Test]

  public function it_throws_exception_if_entry_is_published()
  {
    $this->expectException(DomainException::class);

    $request = Mockery::mock(DataEntryRequest::class);
    $request->shouldReceive('entryId')->andReturn(10);
    $request->shouldReceive('projectId')->andReturn(1);
    $request->shouldReceive('dataTypeId')->andReturn(2);

    $entry = (object)['status' => 'published'];

    $entries = Mockery::mock('App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface');
    $entries->shouldReceive('findForProjectOrFail')
      ->andReturn($entry);

    $service = $this->makeService(['entries' => $entries]);

    $service->update($request, new CreateDataEntryDto([], null, null, 'draft'), 5);
  }

  #[Test]

  public function it_runs_full_update_flow()
  {
    Event::fake();

    $request = Mockery::mock(DataEntryRequest::class);
    $request->shouldReceive('entryId')->andReturn(10);
    $request->shouldReceive('projectId')->andReturn(1);
    $request->shouldReceive('dataTypeId')->andReturn(2);
    $request->shouldReceive('filesInput')->andReturn(['file']);

    $entry = new DataEntry();
    $entry->status = 'draft';

    // mock load() فقط
    $entry = Mockery::mock($entry)->makePartial();
    $entry->shouldReceive('load')->andReturnSelf();

    $entries = Mockery::mock('App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface');
    $entries->shouldReceive('findForProjectOrFail')
      ->once()
      ->with(10, 1)
      ->andReturn($entry);

    $mergeFiles = Mockery::mock('App\Domains\CMS\Actions\Data\MergeFilesAction');
    $mergeFiles->shouldReceive('execute')
      ->once()
      ->andReturn(['merged']);

    $normalize = Mockery::mock('App\Domains\CMS\Actions\Data\NormalizeScheduledAtAction');
    $normalize->shouldReceive('execute')
      ->once()
      ->andReturn('normalized');

    $validate = Mockery::mock('App\Domains\CMS\Actions\Data\ValidateFieldsAction');
    $validate->shouldReceive('execute')->once();

    $resolveState = Mockery::mock('App\Domains\CMS\Actions\Data\ResolveStateAction');
    $resolveState->shouldReceive('execute')->once();

    $deleteValues = Mockery::mock('App\Domains\CMS\Actions\Data\DeleteValuesAction');
    $deleteValues->shouldReceive('execute')->once();

    $insertValues = Mockery::mock('App\Domains\CMS\Actions\Data\InsertValuesAction');
    $insertValues->shouldReceive('execute')->once();

    $handleSeo = Mockery::mock('App\Domains\CMS\Actions\Data\HandleSeoAction');
    $handleSeo->shouldReceive('execute')->once();

    $handleRelations = Mockery::mock('App\Domains\CMS\Actions\Data\HandleRelationsAction');
    $handleRelations->shouldReceive('execute')->once();

    DB::shouldReceive('transaction')
      ->once()
      ->andReturnUsing(function ($callback) {
        return $callback();
      });

    $service = $this->makeService([
      'entries' => $entries,
      'mergeFiles' => $mergeFiles,
      'normalizeScheduledAt' => $normalize,
      'validateFields' => $validate,
      'resolveState' => $resolveState,
      'deleteValues' => $deleteValues,
      'insertValues' => $insertValues,
      'handleSeo' => $handleSeo,
      'handleRelations' => $handleRelations,
    ]);

    $dto = new CreateDataEntryDto(values: [], seo: null, relations: null, status: 'draft');

    $result = $service->update($request, $dto, 5);

    $this->assertSame($entry, $result);

    Event::assertDispatched(EntryChanged::class);
  }

  #[Test]
  public function it_calls_delete_entry()
  {
    $delete = Mockery::mock('App\Domains\CMS\Actions\Data\DeleteDataEntryAction');
    $delete->shouldReceive('execute')
      ->once()
      ->with(10, 1);

    $service = $this->makeService(['deleteEntry' => $delete]);

    $this->assertNull($service->destroy(10, 1));
  }
}
