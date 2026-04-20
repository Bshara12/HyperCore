<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\VerifyProjectJoinRequest;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtService;
use App\Services\ProjectServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProjectController extends Controller
{
    protected $jwtService;
    protected $projectService;

    public function __construct(JwtService $jwtService, ProjectServices $projectServices)
    {
        $this->jwtService = $jwtService;
        $this->projectService = $projectServices;
    }

    public function sign_in_project(Request $request, $projectId) {
        $token = $request->bearerToken();
        $decode = $this->jwtService->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $user = User::find($decode->sub);

        $response = $this->exsists_in_project($user->id, $projectId);
        if(!$response) {
            return response()->json([
                'user_data' => $user,
                'project_id' => $projectId
            ]);
        }

        return response()->json([
            'message' => 'You are already a member of this project'
        ]);
    }

    public function exsists_in_project($userId, $projectId):bool {
        $response = Http::withHeaders([
        'X-Project-Key' => $projectId,
        ])->post('http://localhost:8001/api/check-project-access', [
            'user_id' => $userId
        ]);

        $data = $response->json('has_access');
        return $data;
    }

    // Temp
    public function select(Request $request) {
        $userId = $request->auth_user_id;
        $projectId = $request->project_id;

        $membership = DB::table('project_user')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        $project = Project::findOrFail($projectId);

        if(!$membership && $project->is_public) {
            $memberRole = Role::where('name', 'member')->first();

            DB::table('project_user')->insert([
                'project_id' => $projectId,
                'user_id' => $userId,
                'role_id' => $memberRole->id,
            ]);

            $roleName = 'member';
        }else if($membership) {
            $role = Role::find($membership->role_id);
            $roleName = $role->name;
        } else {
            return response()->json([
                'error' => 'Access Denied!',
            ],403);
        }

        $projectToken = $this->jwtService->generateProjectToken($userId, $projectId, $roleName);

        return response()->json([
            'project_token' => $projectToken
        ]);
    }
}
