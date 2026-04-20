<?php

namespace App\Repositories;

interface OperationRepositoryInteface {
    public function getAllUsers();
    public function assginRoleToUser(int $userId, int $roleId);
    public function removeRoleFromUser(int $userId);
    public function addPermession($permession);
    public function assginPermToRole($permId, $roleId);
    public function removePermFromRole($permId, $roleId);
    public function getAllRoles();
    public function getAllPermissions();
}
