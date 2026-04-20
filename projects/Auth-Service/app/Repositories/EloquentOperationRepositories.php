<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class EloquentOperationRepositories implements OperationRepositoryInteface {
    public function getAllUsers() {
        return User::all();
    }
    public function assginRoleToUser(int $userId, int $roleId) {
        $assignment = DB::table('role_user')->where('user_id', $userId)->first();
        if(!empty($assignment)){
            return DB::table('role_user')->where('user_id', $userId)->update([
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return DB::table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function removeRoleFromUser(int $userId) {
        $remove = DB::table('role_user')->where('user_id', $userId)->update([
            'role_id' => 4
        ]);

        return $remove;
    }

    public function addPermession($permession) {
        $perm = DB::table('permessions')->where('name', $permession)->first();
        if(!empty($perm)) {
            throw new Exception('This permession is already exsist');
        }

        return DB::table('permessions')->insert([
            'name' => $permession,
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function assginPermToRole($permId, $roleId) {
        return DB::table('permession_role')->updateOrInsert([
            'permession_id' => $permId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function removePermFromRole($permId, $roleId) {
        return DB::table('permession_role')
            ->where('role_id', $roleId)
            ->where('permession_id', $permId)
            ->delete();
    }

    public function getAllRoles(){
        return DB::table('roles')->get('name');
    }

    public function getAllPermissions() {
        return DB::table('permessions')->get('name');
    }
}

