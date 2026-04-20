<?php

namespace App\Services;

use App\Repositories\OperationRepositoryInteface;

class OperationServices {
    protected $operations;
    public function __construct(OperationRepositoryInteface $operationRepositoryInteface)
    {
        $this->operations = $operationRepositoryInteface;
    }
    public function getUsersService() {
        return $this->operations->getAllUsers();
    }

    public function assginRoleService(array $data) {
        return $this->operations->assginRoleToUser($data['user_id'], $data['role_id']);
    }

    public function removeRoleService(array $data) {
        return $this->operations->removeRoleFromUser($data['user_id']);
    }

    public function addPermessionService(array $data) {
        return $this->operations->addPermession($data['permession']);
    }

    public function assginPermToRoleService(array $data) {
        return $this->operations->assginPermToRole($data['permession_id'], $data['role_id']);
    }

    public function removePermToRoleService(array $data) {
        return $this->operations->removePermFromRole($data['permession_id'], $data['role_id']);
    }

    public function getAllRolesService() {
        return $this->operations->getAllRoles();
    }

    public function getAllPermissionsService() {
        return $this->operations->getAllPermissions();
    }
}
