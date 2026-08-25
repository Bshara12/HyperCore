<?php

use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;
use App\Http\Middleware\EnsureProjectAccess;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/*
 | ResolveProject answers "which project", AuthUserMiddleware answers "who" —
 | and nothing used to connect the two, so any valid token plus any
 | X-Project-Key returned that project's analytics.
 */

uses(RefreshDatabase::class);

function runAccessMiddleware(Project $project, ?array $user, ?bool $isMember = false)
{
    app()->instance('currentProject', $project);

    $members = Mockery::mock(ProjectUserRepositoryInterface::class);
    $members->shouldReceive('exists')->andReturn((bool) $isMember)->byDefault();

    $request = Request::create('/api/cms/analytics/projectOwner', 'GET');
    $request->attributes->set('auth_user', $user);

    return (new EnsureProjectAccess($members))->handle(
        $request,
        fn () => response()->json(['reached' => true])
    );
}

test('the project owner is allowed through', function () {
    $project = Project::factory()->create(['owner_id' => 51]);

    $response = runAccessMiddleware($project, ['id' => 51]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['reached' => true]);
});

test('a member of the project is allowed through', function () {
    $project = Project::factory()->create(['owner_id' => 51]);

    $response = runAccessMiddleware($project, ['id' => 52], isMember: true);

    expect($response->getStatusCode())->toBe(200);
});

test('an unrelated user is refused', function () {
    // The exact case reproduced against the live API: user 52 read project 18's
    // analytics just by sending its project key.
    $project = Project::factory()->create(['owner_id' => 51]);

    $response = runAccessMiddleware($project, ['id' => 52], isMember: false);

    // 404 rather than 403: a caller with no access should not learn the project
    // exists.
    expect($response->getStatusCode())->toBe(404);
});

test('a request with no authenticated user is refused', function () {
    $project = Project::factory()->create(['owner_id' => 51]);

    $response = runAccessMiddleware($project, null);

    expect($response->getStatusCode())->toBe(401);
});
