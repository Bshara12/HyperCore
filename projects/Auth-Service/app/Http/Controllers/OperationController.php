<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPermissionRequest;
use App\Http\Requests\AssignPermissionToRoleRequest;
use App\Http\Requests\AssignRoleForProjectRequest;
use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\RemovePermissionFromRoleRequest;
use App\Http\Requests\RemoveRoleForProjectRequest;
use App\Http\Requests\RemoveRoleRequest;
use App\Services\OperationServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    protected $operations;

    public function __construct(OperationServices $operationServices)
    {
        $this->operations = $operationServices;
    }

    public function getAllUsers(Request $request)
    {
        $userId = $this->authUserId($request);

        // ✅ كانت isSuperAdmin — admin امتص هذه الصلاحية أيضاً
        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        $users = $this->operations->getUsersService();

        return response()->json(['message' => 'Plataform Users:', 'data' => $users], 200);
    }

    public function assginRoleToUser(AssignRoleRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        try {
            $data = $request->only(['user_id', 'role_id']);
            $assignment = $this->operations->assginRoleService($data, $userId);

            if ($assignment) {
                return response()->json(['message' => 'Done'], 200);
            }

            return response()->json(['message' => 'Somthig went wrong!'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function removeRoleFromUser(RemoveRoleRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        try {
            $data = $request->only(['user_id']);
            $assignment = $this->operations->removeRoleService($data, $userId);

            if ($assignment) {
                return response()->json(['message' => 'Done'], 200);
            }

            return response()->json(['message' => 'Somthig went wrong!'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function add_permession(AddPermissionRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        $data = $request->only(['permession']);
        $done = $this->operations->addPermessionService($data);

        if ($done) {
            return response()->json(['message' => 'The permession added successfuly']);
        }

        return response()->json(['message' => 'Something went wrong, Try again!']);
    }

    public function assign_permession_to_role(AssignPermissionToRoleRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        $data = $request->only(['permession_id', 'role_id']);
        $assignment = $this->operations->assginPermToRoleService($data);

        if ($assignment) {
            return response()->json(['message' => 'Done'], 200);
        }

        return response()->json(['message' => 'Somthig went wrong!'], 404);
    }

    public function remove_permession_from_role(RemovePermissionFromRoleRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        $data = $request->only(['permession_id', 'role_id']);
        $assignment = $this->operations->removePermToRoleService($data);

        if ($assignment) {
            return response()->json(['message' => 'Done'], 200);
        }

        return response()->json(['message' => 'Somthig went wrong!'], 404);
    }

    public function getAllRoles()
    {
        $roles = $this->operations->getAllRolesService();

        if (! empty($roles)) {
            return response()->json(['roles' => $roles]);
        }

        return response()->json(['message' => 'Ther is no roles']);
    }

    public function getAllPermissions()
    {
        $permissions = $this->operations->getAllPermissionsService();

        if (! empty($permissions)) {
            return response()->json(['permissions' => $permissions]);
        }

        return response()->json(['message' => 'Ther is no permissions']);
    }

    // ✅ إعادة تسمية: authorizeSuperAdmin → authorizeAdminOrHyperCore
    private function authorizeAdminOrHyperCore(Request $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isAdminOrHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        return $userId;
    }

    public function createRole(CreateRoleRequest $request)
    {
        $auth = $this->authorizeAdminOrHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $role = $this->operations->createRoleService($request->only(['name', 'project_id']));

            return response()->json(['message' => 'Role created successfully', 'data' => $role], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function createPermission(CreatePermissionRequest $request)
    {
        $auth = $this->authorizeAdminOrHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $permission = $this->operations->createPermissionService($request->only(['permession', 'project_id']));

            return response()->json(['message' => 'Permission created successfully', 'data' => $permission], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function assignRoleToUserForProject(AssignRoleForProjectRequest $request, int $projectId)
    {
        $auth = $this->authorizeAdminOrHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $data = $request->only(['user_id', 'role_id']);
            $data['project_id'] = $projectId;
            $this->operations->assignRoleToUserForProjectService($data);

            return response()->json(['message' => 'Done'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function removeRoleFromUserForProject(RemoveRoleForProjectRequest $request, int $projectId)
    {
        $auth = $this->authorizeAdminOrHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $this->operations->removeRoleFromUserForProjectService([
                'user_id' => $request->input('user_id'),
                'project_id' => $projectId,
            ]);

            return response()->json(['message' => 'Done'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function getRoles(Request $request)
    {
        $roles = $this->operations->getRolesService(
            $request->query('project_id') ? (int) $request->query('project_id') : null
        );

        return response()->json(['data' => $roles]);
    }

    public function getPermissions(Request $request)
    {
        $permissions = $this->operations->getPermissionsService(
            $request->query('project_id') ? (int) $request->query('project_id') : null
        );

        return response()->json(['data' => $permissions]);
    }
}
