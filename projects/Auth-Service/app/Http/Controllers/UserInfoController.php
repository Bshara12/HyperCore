<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Routing\Controller;

class UserInfoController extends Controller
{
    public function show($id)
    {

        $user = User::with('projects')->find($id);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ],404);
        }

        return response()->json([

            'id' => $user->id,

            'email' => $user->email,

            'projects' => $user->projects->map(function ($project) {


                $role = Role::find($project->pivot->role_id);

                return [
                    'project_id' => $project->id,
                    'role' => $role?->name
                ];

            })


        ]);
    }
}
