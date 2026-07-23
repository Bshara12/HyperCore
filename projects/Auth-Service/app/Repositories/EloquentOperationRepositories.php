<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;

class EloquentOperationRepositories implements OperationRepositoryInteface
{
    // ─── Users ──────────────────────────────────────────────────────────────

    public function getAllUsers()
    {
        return \App\Models\User::all();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Role Assignment — عام (النظام ككل)
    // ═══════════════════════════════════════════════════════════════════════

    public function assginRoleToUser(int $userId, int $roleId)
    {
        // whereNull('project_id') يحدد أننا نبحث عن الإسناد "العام" فقط لهذا المستخدم
        $assignment = DB::table('role_user')
            ->where('user_id', $userId)
            ->whereNull('project_id')
            ->first();

        if (! empty($assignment)) {
            return DB::table('role_user')
                ->where('user_id', $userId)
                ->whereNull('project_id')
                ->update([
                    'role_id' => $roleId,
                    'updated_at' => now(),
                ]);
        }

        return DB::table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'project_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function removeRoleFromUser(int $userId)
    {
        // نُبقي على السلوك الأصلي: تصفير إلى role_id=4 (الدور الافتراضي) بدلاً من الحذف
        return DB::table('role_user')
            ->where('user_id', $userId)
            ->whereNull('project_id')
            ->update([
                'role_id' => 4,
                'updated_at' => now(),
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Role Assignment — خاص بمشروع محدد
    // ═══════════════════════════════════════════════════════════════════════

    public function assignRoleToUserForProject(int $userId, int $roleId, int $projectId)
    {
        $assignment = DB::table('role_user')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();

        if (! empty($assignment)) {
            return DB::table('role_user')
                ->where('user_id', $userId)
                ->where('project_id', $projectId)
                ->update([
                    'role_id' => $roleId,
                    'updated_at' => now(),
                ]);
        }

        return DB::table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function removeRoleFromUserForProject(int $userId, int $projectId)
    {
        /*
         | هنا نحذف السطر بالكامل بدلاً من التصفير إلى role_id=4
         | لأنه لا يوجد "دور افتراضي" ذو معنى ضمن سياق مشروع محدد
         | (بخلاف الحالة العامة حيث role_id=4 هو "زائر/بلا صلاحيات" منطقي على مستوى النظام)
         | حذف السطر يعني: المستخدم لم يعد له أي دور داخل هذا المشروع تحديداً
         */
        return DB::table('role_user')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->delete();
    }

    public function findUserRoleAssignment(int $userId, ?int $projectId)
    {
        return DB::table('role_user')
            ->where('user_id', $userId)
            ->where(function ($q) use ($projectId) {
                $projectId === null
                    ? $q->whereNull('project_id')
                    : $q->where('project_id', $projectId);
            })
            ->first();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Roles Catalog
    // ═══════════════════════════════════════════════════════════════════════

    public function createRole(string $name, ?int $projectId = null)
    {
        $exists = $this->findRoleByNameAndProject($name, $projectId);

        if ($exists) {
            throw new Exception(
                $projectId
                    ? 'This role name already exists for this project.'
                    : 'This role name already exists at the system level.'
            );
        }

        $id = DB::table('roles')->insertGetId([
            'name' => $name,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('roles')->where('id', $id)->first();
    }

    public function findRoleByNameAndProject(string $name, ?int $projectId = null)
    {
        return DB::table('roles')
            ->where('name', $name)
            ->where(function ($q) use ($projectId) {
                $projectId === null
                    ? $q->whereNull('project_id')
                    : $q->where('project_id', $projectId);
            })
            ->first();
    }

    public function findRoleById(int $roleId)
    {
        return DB::table('roles')->where('id', $roleId)->first();
    }

    public function getAllRoles(?int $projectId = null)
    {
        $query = DB::table('roles');

        /*
         | بدون تمرير projectId: نُرجع كل الأدوار (سلوك متوافق مع الكود القديم)
         | مع تمرير projectId: نُرجع أدوار هذا المشروع + الأدوار العامة
         | (لأن مشروعاً معيناً يجب أن يرى الأدوار العامة القابلة للاستخدام فيه أيضاً)
         */
        if ($projectId !== null) {
            $query->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)->orWhereNull('project_id');
            });
        }

        return $query->get(['id', 'name', 'project_id']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Permissions Catalog
    // ═══════════════════════════════════════════════════════════════════════

    public function addPermession(string $permession, ?int $projectId = null)
    {
        $exists = $this->findPermissionByNameAndProject($permession, $projectId);

        if (! empty($exists)) {
            throw new Exception(
                $projectId
                    ? 'This permession already exists for this project.'
                    : 'This permession is already exsist'
            );
        }

        $id = DB::table('permessions')->insertGetId([
            'name' => $permession,
            'project_id' => $projectId,
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('permessions')->where('id', $id)->first();
    }

    public function findPermissionByNameAndProject(string $name, ?int $projectId = null)
    {
        return DB::table('permessions')
            ->where('name', $name)
            ->where(function ($q) use ($projectId) {
                $projectId === null
                    ? $q->whereNull('project_id')
                    : $q->where('project_id', $projectId);
            })
            ->first();
    }

    public function findPermissionById(int $permId)
    {
        return DB::table('permessions')->where('id', $permId)->first();
    }

    public function getAllPermissions(?int $projectId = null)
    {
        $query = DB::table('permessions');

        if ($projectId !== null) {
            $query->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)->orWhereNull('project_id');
            });
        }

        return $query->get(['id', 'name', 'project_id']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Permission <-> Role
    // ═══════════════════════════════════════════════════════════════════════

    public function assginPermToRole(int $permId, int $roleId)
    {
        /*
         | 🐛 إصلاح: الكود الأصلي كان يستخدم updateOrInsert بمصفوفة واحدة فقط
         | تحتوي created_at/updated_at ضمن شروط البحث، وبما أن قيمة now()
         | تتغيّر في كل استدعاء، فإن الشرط لن يتطابق أبداً مع أي سطر سابق
         | فيُنشئ تكراراً في كل مرة بدلاً من تحديث السطر الموجود.
         | الحل: فصل شروط البحث (permession_id + role_id) عن القيم المُحدَّثة.
         */
        return DB::table('permession_role')->updateOrInsert(
            [
                'permession_id' => $permId,
                'role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function removePermFromRole(int $permId, int $roleId)
    {
        return DB::table('permession_role')
            ->where('role_id', $roleId)
            ->where('permession_id', $permId)
            ->delete();
    }
}
