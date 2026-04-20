<?php

namespace Tests\Unit\Action;

use Tests\TestCase;
use Mockery;
use App\Models\Project;
use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

class ShowProjectActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAction($repository)
    {
        $action = Mockery::mock(ShowProjectAction::class, [$repository])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $action->shouldReceive('run')
            ->andReturnUsing(fn($callback) => $callback());

        return $action;
    }

    #[Test]
    public function it_returns_project_successfully()
    {
        $project = new Project(['name' => 'My Project']);

        $repository = Mockery::mock(ProjectRepositoryInterface::class);
        $repository->shouldReceive('find')
            ->once()
            ->with($project)
            ->andReturn($project);

        $action = $this->makeAction($repository);

        $result = $action->execute($project);

        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('My Project', $result->name);
    }

    #[Test]
    public function it_throws_exception_when_repository_fails()
    {
        $project = new Project(['name' => 'Fail']);

        $repository = Mockery::mock(ProjectRepositoryInterface::class);
        $repository->shouldReceive('find')
            ->once()
            ->andThrow(new \Exception('Project not found'));

        $action = $this->makeAction($repository);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Project not found');

        $action->execute($project);
    }
}
