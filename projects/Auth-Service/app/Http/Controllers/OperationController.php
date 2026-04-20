<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use App\Services\OperationServices;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    protected $operations;
    protected $jwt;

    public function __construct(OperationServices $operationServices, JwtService $jwtService)
    {
        $this->operations = $operationServices;
        $this->jwt = $jwtService;
    }

    public function getAllUsers(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        if($user) {
            if(!User::is_super_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }

            $users = $this->operations->getUsersService();

            return response()->json([
                'message' => 'Plataform Users:',
                'data' => $users,
            ], 200);
        }

        return response()->json([
            'message' => 'Somthig went wrong! User not found',
        ], 404);
    }

    public function assginRoleToUser(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        $data = $request->only(['user_id', 'role_id']);

        if($user) {
            if(!User::is_super_admin($user) && !User::is_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }

            $assignment = $this->operations->assginRoleService($data);

            if($assignment) {
                return response()->json([
                    'message' => 'Done',
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Somthig went wrong!',
        ], 404);
    }

    public function removeRoleFromUser(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        $data = $request->only(['user_id']);

        if($user) {
            if(!User::is_super_admin($user) && !User::is_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }

            $assignment = $this->operations->removeRoleService($data);

            if($assignment) {
                return response()->json([
                    'message' => 'Done',
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Somthig went wrong!',
        ], 404);
    }

    public function add_permession(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        $data = $request->only(['permession']);

        if($user) {
            if(!User::is_super_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }
            $done = $this->operations->addPermessionService($data);
            if($done) {
                return response()->json([
                    'message' => 'The permession added successfuly',
                ]);
            }

            return response()->json([
                'message' => 'Something went wrong, Try again!'
            ]);
        }
        return response()->json([
            'message' => 'Not authorized',
        ], 401);
    }

    public function assign_permession_to_role(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        $data = $request->only(['permession_id', 'role_id']);

        if($user) {
            if(!User::is_super_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }

            $assignment = $this->operations->assginPermToRoleService($data);

            if($assignment) {
                return response()->json([
                    'message' => 'Done',
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Somthig went wrong!',
        ], 404);
    }
    public function remove_permession_from_role(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        $data = $request->only(['permession_id', 'role_id']);

        if($user) {
            if(!User::is_super_admin($user)) {
                return response()->json([
                    'message' => 'Not authorized',
                ], 401);
            }

            $assignment = $this->operations->removePermToRoleService($data);

            if($assignment) {
                return response()->json([
                    'message' => 'Done',
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Somthig went wrong!',
        ], 404);
    }

    public function getAllRoles() {
        $roles = $this->operations->getAllRolesService();
        if(!empty($roles)) {
            return response()->json([
                'roles' => $roles
            ]);
        }
        return response()->json([
            'message' => 'Ther is no roles'
        ]);
    }

    public function getAllPermissions() {
        $permissions = $this->operations->getAllPermissionsService();
        if(!empty($permissions)) {
            return response()->json([
                'permissions' => $permissions
            ]);
        }
        return response()->json([
            'message' => 'Ther is no permissions'
        ]);
    }
}
