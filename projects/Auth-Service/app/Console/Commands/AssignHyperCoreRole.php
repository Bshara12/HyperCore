<?php

namespace App\Console\Commands;

use App\Repositories\OperationRepositoryInteface;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Console\Command;

class AssignHyperCoreRole extends Command
{
    protected $signature = 'hypercore:assign {email : بريد مستخدم مسجّل مسبقاً بالنظام}';

    protected $description = 'إسناد دور hyper_core (مطوّر نظام، صلاحيات كاملة) لمستخدم موجود. يُنفَّذ حصراً عبر السيرفر مباشرة.';

    public function handle(UserRepositoryInterface $users, OperationRepositoryInteface $operations): int
    {
        $email = $this->argument('email');
        $user = $users->findByEmail($email);

        if (! $user) {
            $this->error("لا يوجد مستخدم بهذا البريد: {$email}");

            return self::FAILURE;
        }

        if (! $this->confirm("إسناد دور hyper_core لـ \"{$user->name}\" ({$user->email})؟ هذا يمنحه صلاحيات كاملة على النظام.")) {
            $this->info('تم الإلغاء.');

            return self::SUCCESS;
        }

        $role = $operations->findRoleByNameAndProject('hyper_core', null);

        if (! $role) {
            $this->error('دور hyper_core غير موجود. شغّلي RolesAndPermissionsSeeder أولاً.');

            return self::FAILURE;
        }

        $operations->assginRoleToUser($user->id, $role->id);

        $this->info("تم إسناد دور hyper_core لـ {$user->email} بنجاح.");

        return self::SUCCESS;
    }
}
