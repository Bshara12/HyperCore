<?php

namespace App\Repositories;

interface OperationRepositoryInteface
{
    // ─── Users ──────────────────────────────────────────────────────────────
    public function getAllUsers();

    // ─── Role Assignment — عام (على مستوى النظام) ────────────────────────
    public function assginRoleToUser(int $userId, int $roleId);

    public function removeRoleFromUser(int $userId);

    // ─── Role Assignment — خاص بمشروع محدد ────────────────────────────────
    public function assignRoleToUserForProject(int $userId, int $roleId, int $projectId);

    public function removeRoleFromUserForProject(int $userId, int $projectId);

    public function findUserRoleAssignment(int $userId, ?int $projectId);

    // ✅ جديد: فحص امتلاك دور معيّن بالاسم (بدل الاعتماد على رقم مكتوب يدوياً)
    public function userHasRole(int $userId, string $roleName, ?int $projectId = null): bool;

    public function userHasAnyRole(int $userId, array $roleNames, ?int $projectId = null): bool;

    // ─── Roles Catalog ──────────────────────────────────────────────────────
    public function createRole(string $name, ?int $projectId = null);

    public function findRoleByNameAndProject(string $name, ?int $projectId = null);

    public function findRoleById(int $roleId);

    public function getAllRoles(?int $projectId = null);

    // ─── Permissions Catalog ────────────────────────────────────────────────
    public function addPermession(string $permession, ?int $projectId = null);

    public function findPermissionByNameAndProject(string $name, ?int $projectId = null);

    public function findPermissionById(int $permId);

    public function getAllPermissions(?int $projectId = null);

    // ─── Permission <-> Role ────────────────────────────────────────────────
    public function assginPermToRole(int $permId, int $roleId);

    public function removePermFromRole(int $permId, int $roleId);

    public function getProjectMembers(int $projectId);
}
