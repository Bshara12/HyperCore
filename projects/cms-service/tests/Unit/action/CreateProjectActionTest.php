<?php

namespace Tests\Unit\Action;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Str;
use App\Models\Project;
use App\Models\User;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

class CreateProjectActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_project_successfully()
    {
        $owner = User::factory()->make(['id' => 10]);

        $dto = new CreateProjectDTO(
            name: 'Test Project',
            ownerId: 10,
            supportedLanguages: ['en', 'ar'],
            enabledModules: ['blog', 'shop'],
        );

        $repository = Mockery::mock(ProjectRepositoryInterface::class);

        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['name'] === 'Test Project'
                    && $data['owner_id'] === 10
                    && $data['supported_languages'] === ['en', 'ar']
                    && $data['enabled_modules'] === ['blog', 'shop']
                    && !empty($data['public_id'])
                    && Str::isUuid($data['public_id']);
            }))
            ->andReturn(new Project([
                'name' => 'Test Project',
                'owner_id' => 10,
            ]));

        $action = new CreateProjectAction($repository);

        $result = $action->execute($dto);

        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('Test Project', $result->name);
    }

    #[Test]
    public function it_generates_unique_public_id()
    {
        $dto = new CreateProjectDTO(
            name: 'Another Project',
            ownerId: 5,
            supportedLanguages: null,
            enabledModules: null,
        );

        $repository = Mockery::mock(ProjectRepositoryInterface::class);

        $capturedPublicId = null;

        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use (&$capturedPublicId) {
                $capturedPublicId = $data['public_id'];
                return true;
            }))
            ->andReturn(new Project());

        $action = new CreateProjectAction($repository);

        $action->execute($dto);

        $this->assertNotNull($capturedPublicId);
        $this->assertTrue(Str::isUuid($capturedPublicId));
    }

    #[Test]
    public function it_passes_null_optional_fields_correctly()
    {
        $dto = new CreateProjectDTO(
            name: 'Null Fields Project',
            ownerId: 3,
            supportedLanguages: null,
            enabledModules: null,
        );

        $repository = Mockery::mock(ProjectRepositoryInterface::class);

        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return array_key_exists('supported_languages', $data)
                    && array_key_exists('enabled_modules', $data)
                    && $data['supported_languages'] === null
                    && $data['enabled_modules'] === null;
            }))
            ->andReturn(new Project());

        $action = new CreateProjectAction($repository);

        $this->assertInstanceOf(Project::class, $action->execute($dto));
    }

    #[Test]
    public function it_throws_exception_when_repository_fails()
    {
        $dto = new CreateProjectDTO(
            name: 'Fail Project',
            ownerId: 1,
            supportedLanguages: [],
            enabledModules: [],
        );

        $repository = Mockery::mock(ProjectRepositoryInterface::class);

        $repository->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $action = new CreateProjectAction($repository);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $action->execute($dto);
    }
}
