<?php

namespace App\Services;

use App\Repositories\OperationRepositoryInteface;
use Exception;

class OperationServices
{
    protected $operations;

    public function __construct(OperationRepositoryInteface $operationRepositoryInteface)
    {
        $this->operations = $operationRepositoryInteface;
    }

    // ─── الموجودة سابقاً — بدون أي تغيير في السلوك الخارجي ─────────────────

    public function getUsersService()
    {
        return $this->operations->getAllUsers();
    }

    public function assginRoleService(array $data)
    {
        return $this->operations->assginRoleToUser($data['user_id'], $data['role_id']);
    }

    public function removeRoleService(array $data)
    {
        return $this->operations->removeRoleFromUser($data['user_id']);
    }

    public function addPermessionService(array $data)
    {
        // بدون project_id → صلاحية عامة على مستوى النظام (متوافق مع الاستخدام القديم)
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

    /**
     * أصبحت الآن تستدعي assignPermissionToRoleService الجديدة
     * لتستفيد تلقائياً من التحقق من توافق النطاق (Project Scope)
     * دون الحاجة لتعديل الـ Controller القديم إطلاقاً
     */
    public function assginPermToRoleService(array $data)
    {
        return $this->assignPermissionToRoleService($data);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إنشاء Role — نظام أو مشروع
    // ═══════════════════════════════════════════════════════════════════════

    public function createRoleService(array $data)
    {
        $projectId = $data['project_id'] ?? null;

        return $this->operations->createRole($data['name'], $projectId);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إنشاء Permission — نظام أو مشروع
    // ═══════════════════════════════════════════════════════════════════════

    public function createPermissionService(array $data)
    {
        $projectId = $data['project_id'] ?? null;

        return $this->operations->addPermession($data['permession'], $projectId);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: ربط صلاحية بـ Role مع التحقق من توافق النطاق
    // ═══════════════════════════════════════════════════════════════════════

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

        /*
         | 🔒 قاعدة التوافق:
         | إذا كانت الصلاحية خاصة بمشروع معين (project_id != null)،
         | فلا يمكن ربطها إلا بـ role ينتمي لنفس المشروع بالضبط.
         | صلاحية عامة (project_id = null) يمكن ربطها بأي role (عام أو خاص بمشروع).
         */
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

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إسناد Role لمستخدم ضمن مشروع محدد
    // ═══════════════════════════════════════════════════════════════════════

    public function assignRoleToUserForProjectService(array $data)
    {
        $role = $this->operations->findRoleById($data['role_id']);

        if (! $role) {
            throw new Exception('Role not found.');
        }

        /*
         | 🔒 قاعدة التوافق:
         | - role عام (project_id = null) → يمكن إسناده ضمن أي مشروع (استخدام قالب عام)
         | - role خاص بمشروع آخر → مرفوض تماماً
         */
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

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: إزالة Role عن مستخدم ضمن مشروع محدد
    // ═══════════════════════════════════════════════════════════════════════

    public function removeRoleFromUserForProjectService(array $data)
    {
        return $this->operations->removeRoleFromUserForProject(
            $data['user_id'],
            $data['project_id']
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // جديد: جلب الأدوار / الصلاحيات (فلترة اختيارية حسب مشروع)
    // ═══════════════════════════════════════════════════════════════════════

    public function getRolesService(?int $projectId = null)
    {
        return $this->operations->getAllRoles($projectId);
    }

    public function getPermissionsService(?int $projectId = null)
    {
        return $this->operations->getAllPermissions($projectId);
    }

    // أضف هذه الـ method داخل الكلاس الموجود

    /**
     * جلب دور عام (project_id = null) بالاسم
     * تُستخدم لجلب دور "user" الافتراضي لإسناده ضمن أي مشروع
     */
    public function findGlobalRoleByNameService(string $name)
    {
        return $this->operations->findRoleByNameAndProject($name, null);
    }

    public function getProjectMembersService(int $projectId)
    {
        return $this->operations->getProjectMembers($projectId);
    }
}
