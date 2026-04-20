<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;

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

  public function all(): \Illuminate\Support\Collection
  {
    return Project::query()->latest()->get();
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

    if (!$project) {
      return [
        'ratings_count' => 0,
        'ratings_avg' => 0
      ];
    }

    return [
      'ratings_count' => $project->ratings_count,
      'ratings_avg' => (float) $project->ratings_avg,
    ];
  }
}
