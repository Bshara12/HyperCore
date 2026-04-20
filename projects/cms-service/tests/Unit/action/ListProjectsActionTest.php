<?php

namespace Tests\Unit\Action;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Collection;
use App\Models\Project;
use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

class ListProjectsActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAction($repository)
    {
        $action = Mockery::mock(ListProjectsAction::class, [$repository])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $action->shouldReceive('run')
            ->andReturnUsing(fn($callback) => $callback());

        return $action;
    }

    #[Test]
    public function it_returns_collection_of_projects()
    {
        $projects = collect([
            new Project(['name' => 'Project 1']),
            new Project(['name' => 'Project 2']),
        ]);

        $repository = Mockery::mock(ProjectRepositoryInterface::class);
        $repository->shouldReceive('all')
            ->once()
            ->andReturn($projects);

        $action = $this->makeAction($repository);

        $result = $action->execute();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals('Project 1', $result[0]->name);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_projects_exist()
    {
        $repository = Mockery::mock(ProjectRepositoryInterface::class);
        $repository->shouldReceive('all')
            ->once()
            ->andReturn(collect());

        $action = $this->makeAction($repository);

        $result = $action->execute();

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_throws_exception_when_repository_fails()
    {
        $repository = Mockery::mock(ProjectRepositoryInterface::class);
        $repository->shouldReceive('all')
            ->once()
            ->andThrow(new \Exception('Database failure'));

        $action = $this->makeAction($repository);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database failure');

        $action->execute();
    }
}
