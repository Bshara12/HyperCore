<?php

namespace App\Services;

use App\Events\SystemLogEvent;
use App\Repositories\OperationRepositoryInteface;
use App\Repositories\UserRepositoryInterface;
use Exception;

class OperationServices
{
    protected $operations;
    protected $users;

    public function __construct(
        OperationRepositoryInteface $operationRepositoryInteface,
        UserRepositoryInterface $userRepository
    ) {
        $this->operations = $operationRepositoryInteface;
        $this->users = $userRepository;
    }

    public function getUsersService()
    {
        return $this->operations->getAllUsers();
    }

    /**
     * ✅ صار يستقبل $actingUserId: يمنع أي admin عادي من إسناد دور hyper_core لأي أحد
     * (ثغرة تصعيد صلاحيات كانت موجودة أصلاً حتى مع super_admin القديم)
     */
    public function assginRoleService(array $data, int $actingUserId)
    {
        $targetRole = $this->operations->findRoleById($data['role_id']);

        if ($targetRole && $targetRole->name === 'hyper_core' && ! $this->isHyperCore($actingUserId)) {
            throw new Exception('Only hyper_core can assign the hyper_core role.');
        }

        return $this->operations->assginRoleToUser($data['user_id'], $data['role_id']);
    }

    /**
     * ✅ صار يستقبل $actingUserId: يمنع أي admin عادي من تجريد حساب hyper_core من دوره
     */
    public function removeRoleService(array $data, int $actingUserId)
    {
        if ($this->operations->userHasRole($data['user_id'], 'hyper_core') && ! $this->isHyperCore($actingUserId)) {
            throw new Exception('Only hyper_core can modify another hyper_core account.');
        }

        return $this->operations->removeRoleFromUser($data['user_id']);
    }

    public function addPermessionService(array $data)
    {
        return $this->operations->addPermession($data['permession']);
    }

    public function removePermToRoleService(array $data)
    {
        return $this->operations->removePermFromRole($data['permession_id'], $data['role_id']);
    }

    public function getAllRolesService()
    {
        return $this->operations->getAllRoles();
    }

    public function getAllPermissionsService()
    {
        return $this->operations->getAllPermissions();
    }

    public function assginPermToRoleService(array $data)
    {
        return $this->assignPermissionToRoleService($data);
    }

    public function createRoleService(array $data)
    {
        $projectId = $data['project_id'] ?? null;
        return $this->operations->createRole($data['name'], $projectId);
    }

    public function createPermissionService(array $data)
    {
        $projectId = $data['project_id'] ?? null;
        return $this->operations->addPermession($data['permession'], $projectId);
    }

    public function assignPermissionToRoleService(array $data)
    {
        $role = $this->operations->findRoleById($data['role_id']);
        $permission = $this->operations->findPermissionById($data['permession_id']);

        if (! $role) {
            throw new Exception('Role not found.');
        }

        if (! $permission) {
            throw new Exception('Permission not found.');
        }

        if (
            $permission->project_id !== null
            && (int) $permission->project_id !== (int) $role->project_id
        ) {
            throw new Exception(
                'Cannot assign a project-scoped permission to a role from a different project or a global role.'
            );
        }

        return $this->operations->assginPermToRole($data['permession_id'], $data['role_id']);
    }

    public function assignRoleToUserForProjectService(array $data)
    {
        $role = $this->operations->findRoleById($data['role_id']);

        if (! $role) {
            throw new Exception('Role not found.');
        }

        if (
            $role->project_id !== null
            && (int) $role->project_id !== (int) $data['project_id']
        ) {
            throw new Exception('This role belongs to a different project.');
        }

        return $this->operations->assignRoleToUserForProject(
            $data['user_id'],
            $data['role_id'],
            $data['project_id']
        );
    }

    public function removeRoleFromUserForProjectService(array $data)
    {
        return $this->operations->removeRoleFromUserForProject(
            $data['user_id'],
            $data['project_id']
        );
    }

    public function getRolesService(?int $projectId = null)
    {
        return $this->operations->getAllRoles($projectId);
    }

    public function getPermissionsService(?int $projectId = null)
    {
        return $this->operations->getAllPermissions($projectId);
    }

    public function findGlobalRoleByNameService(string $name)
    {
        return $this->operations->findRoleByNameAndProject($name, null);
    }

    public function getProjectMembersService(int $projectId)
    {
        return $this->operations->getProjectMembers($projectId);
    }

    // ✅ إعادة تسمية: isSuperAdmin → isHyperCore
    public function isHyperCore(int $userId): bool
    {
        return $this->operations->userHasRole($userId, 'hyper_core');
    }

    public function isAdmin(int $userId): bool
    {
        return $this->operations->userHasRole($userId, 'admin');
    }

    public function isOwner(int $userId): bool
    {
        return $this->operations->userHasRole($userId, 'owner');
    }

    // ✅ إعادة تسمية: isAdminOrSuperAdmin → isAdminOrHyperCore
    public function isAdminOrHyperCore(int $userId): bool
    {
        return $this->operations->userHasAnyRole($userId, ['admin', 'hyper_core']);
    }

    public function assignDefaultRegistrationRole(int $userId): void
    {
        $role = $this->operations->findRoleByNameAndProject('admin', null);

        if (! $role) {
            throw new Exception('Default "admin" role not found. Please run RolesAndPermissionsSeeder.');
        }

        $this->operations->assginRoleToUser($userId, $role->id);
    }

    /**
     * ✅ جديد: حذف مستخدم نهائياً (hard delete)
     * يُستدعى حصراً من HyperCoreController بعد التحقق من isHyperCore
     */
    public function deleteUser(int $targetUserId, int $actingUserId): void
    {
        if ($targetUserId === $actingUserId) {
            throw new Exception('You cannot delete your own account.');
        }

        $user = $this->users->findById($targetUserId);

        if (! $user) {
            throw new Exception('User not found.');
        }

        $this->users->delete($user);

        event(new SystemLogEvent(
            module: 'auth',
            eventType: 'user_deleted',
            userId: $actingUserId,
            entityType: 'user',
            entityId: $targetUserId,
        ));
    }
}
