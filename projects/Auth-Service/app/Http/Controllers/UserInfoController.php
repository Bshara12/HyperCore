<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Routing\Controller;

class UserInfoController extends Controller
{
  public function show($id)
  {

    $user = User::with('projects')->find($id);

    if (! $user) {
      return response()->json([
        'error' => 'User not found',
      ], 404);
    }
    /** @var \Illuminate\Database\Eloquent\Collection<int, Project> $projects */
    $projects = $user->projects;
    return response()->json([

      'id' => $user->id,

      'email' => $user->email,

      // 'projects' => $user->projects->map(function ($project) {

      //     $role = Role::find($project->pivot->role_id);

      //     return [
      //         'project_id' => $project->id,
      //         'role' => $role?->name,
      //     ];

      // }),

      'projects' => $projects->map(
        function (Project $project) {

          /** @var \Illuminate\Database\Eloquent\Relations\Pivot&object{role_id:int} $pivot */
          $pivot = $project->pivot;

          return [
            'project_id' => $project->id,
            'role' => Role::find($pivot->role_id)?->name,
          ];
        }
      ),
    ]);
  }
}
