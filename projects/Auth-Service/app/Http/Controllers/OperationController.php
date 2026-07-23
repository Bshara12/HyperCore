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

  public function getAllUsers(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    if ($user) {
      if (! User::is_super_admin($user)) {
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

  public function assginRoleToUser(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    $data = $request->only(['user_id', 'role_id']);

    if ($user) {
      if (! User::is_super_admin($user) && ! User::is_admin($user)) {
        return response()->json([
          'message' => 'Not authorized',
        ], 401);
      }

      $assignment = $this->operations->assginRoleService($data);

      if ($assignment) {
        return response()->json([
          'message' => 'Done',
        ], 200);
      }
    }

    return response()->json([
      'message' => 'Somthig went wrong!',
    ], 404);
  }

  public function removeRoleFromUser(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    $data = $request->only(['user_id']);

    if ($user) {
      if (! User::is_super_admin($user) && ! User::is_admin($user)) {
        return response()->json([
          'message' => 'Not authorized',
        ], 401);
      }

      $assignment = $this->operations->removeRoleService($data);

      if ($assignment) {
        return response()->json([
          'message' => 'Done',
        ], 200);
      }
    }

    return response()->json([
      'message' => 'Somthig went wrong!',
    ], 404);
  }

  public function add_permession(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    $data = $request->only(['permession']);

    if ($user) {
      if (! User::is_super_admin($user)) {
        return response()->json([
          'message' => 'Not authorized',
        ], 401);
      }
      $done = $this->operations->addPermessionService($data);
      if ($done) {
        return response()->json([
          'message' => 'The permession added successfuly',
        ]);
      }

      return response()->json([
        'message' => 'Something went wrong, Try again!',
      ]);
    }

    return response()->json([
      'message' => 'Not authorized',
    ], 401);
  }

  public function assign_permession_to_role(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    $data = $request->only(['permession_id', 'role_id']);

    if ($user) {
      if (! User::is_super_admin($user)) {
        return response()->json([
          'message' => 'Not authorized',
        ], 401);
      }

      $assignment = $this->operations->assginPermToRoleService($data);

      if ($assignment) {
        return response()->json([
          'message' => 'Done',
        ], 200);
      }
    }

    return response()->json([
      'message' => 'Somthig went wrong!',
    ], 404);
  }

  public function remove_permession_from_role(Request $request)
  {
    $token = $request->bearerToken();
    $decode = $this->jwt->validateToken($token);
    // @codeCoverageIgnoreStart
    if (! $decode) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    // @codeCoverageIgnoreEnd

    $user = User::find($decode->sub);

    $data = $request->only(['permession_id', 'role_id']);

    if ($user) {
      if (! User::is_super_admin($user)) {
        return response()->json([
          'message' => 'Not authorized',
        ], 401);
      }

      $assignment = $this->operations->removePermToRoleService($data);

      if ($assignment) {
        return response()->json([
          'message' => 'Done',
        ], 200);
      }
    }

    return response()->json([
      'message' => 'Somthig went wrong!',
    ], 404);
  }

  public function getAllRoles()
  {
    $roles = $this->operations->getAllRolesService();
    if (! empty($roles)) {
      return response()->json([
        'roles' => $roles,
      ]);
    }

    return response()->json([
      'message' => 'Ther is no roles',
    ]);
  }

  public function getAllPermissions()
  {
    $permissions = $this->operations->getAllPermissionsService();
    if (! empty($permissions)) {
      return response()->json([
        'permissions' => $permissions,
      ]);
    }

    return response()->json([
      'message' => 'Ther is no permissions',
    ]);
  }

    // ─── Helper خاص لتفويض Super Admin فقط (يُستخدم في الميثودز الجديدة) ────

    /**
     * يتحقق من التوكن ومن كون المستخدم Super Admin
     * يُرجع: User عند النجاح، أو JsonResponse (401) عند الفشل
     */
    private function authorizeSuperAdmin(Request $request)
    {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);

        if (! $decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);

        if (! $user || ! User::is_super_admin($user)) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        return $user;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إنشاء Role — نظام أو مشروع (project_id اختياري في الـ body)
    // ═══════════════════════════════════════════════════════════════════════

    public function createRole(Request $request)
    {
        $auth = $this->authorizeSuperAdmin($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        try {
            $role = $this->operations->createRoleService(
                $request->only(['name', 'project_id'])
            );

            return response()->json([
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إنشاء Permission — نظام أو مشروع (project_id اختياري في الـ body)
    // ═══════════════════════════════════════════════════════════════════════

    public function createPermission(Request $request)
    {
        $auth = $this->authorizeSuperAdmin($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        try {
            $permission = $this->operations->createPermissionService(
                $request->only(['permession', 'project_id'])
            );

            return response()->json([
                'message' => 'Permission created successfully',
                'data' => $permission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إسناد Role لمستخدم ضمن مشروع محدد
    // ═══════════════════════════════════════════════════════════════════════

    public function assignRoleToUserForProject(Request $request, int $projectId)
    {
        $auth = $this->authorizeSuperAdmin($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
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

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إزالة Role عن مستخدم ضمن مشروع محدد
    // ═══════════════════════════════════════════════════════════════════════

    public function removeRoleFromUserForProject(Request $request, int $projectId)
    {
        $auth = $this->authorizeSuperAdmin($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
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

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: جلب الأدوار (مع فلترة اختيارية بمشروع عبر ?project_id=X)
    // ═══════════════════════════════════════════════════════════════════════

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

