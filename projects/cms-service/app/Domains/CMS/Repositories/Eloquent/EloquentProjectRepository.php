<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Support\Collection;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh();
    }

    public function find(Project $project): Project
    {
        return $project;
    }

    public function findByKey(string $key): Project
    {
        return Project::where('public_id', $key)->firstOrFail();
    }

    public function all(): Collection
    {
        return Project::query()->latest()->get();
    }

    /**
     * Projects one user is entitled to see:
     *   - the ones they own (`owner_id`), and
     *   - the ones they hold a role in, per the Auth Service's
     *     role_user.project_id pivot carried on the request, and
     *   - the ones they joined via `project_user`.
     *
     * All three are needed: CreateProjectAction does not add the owner to
     * `project_user`, and nothing in this service writes that table at all —
     * membership is granted in the Auth Service.
     *
     * @param  int[]  $roleProjectIds
     */
    public function allForUser(
        int $userId,
        array $roleProjectIds = []
    ): Collection {

        return Project::query()
            ->where(function ($query) use ($userId, $roleProjectIds) {

                $query
                    ->where('owner_id', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {

                        $sub->selectRaw('1')
                            ->from('project_user')
                            ->whereColumn('project_user.project_id', 'projects.id')
                            ->where('project_user.user_id', $userId);
                    });

                if (! empty($roleProjectIds)) {
                    $query->orWhereIn('id', $roleProjectIds);
                }
            })
            ->latest()
            ->get();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function findById(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function updateRatingStats(int $id, array $data): void
    {
        Project::where('id', $id)->update([
            'ratings_count' => $data['ratings_count'],
            'ratings_avg' => $data['ratings_avg'],
        ]);
    }

    public function getRatingStats(int $id): array
    {
        $project = Project::select('ratings_count', 'ratings_avg')
            ->where('id', $id)
            ->first();

        if (! $project) {
            return [
                'ratings_count' => 0,
                'ratings_avg' => 0,
            ];
        }

        return [
            'ratings_count' => $project->ratings_count,
            'ratings_avg' => (float) $project->ratings_avg,
        ];
    }
}
