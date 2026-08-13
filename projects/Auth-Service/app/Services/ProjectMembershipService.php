<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\OperationRepositoryInteface;
use App\Repositories\UserRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProjectMembershipService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OperationRepositoryInteface $operationsRepo,
        private readonly OperationServices $operationsService,
        private readonly SessionService $sessions,
        private readonly JwtService $jwt,
    ) {}

    /**
     * تسجيل مستخدم أو تسجيل دخوله ضمن مشروع محدد — Endpoint واحد يجمع الحالتين
     *
     * @throws Exception عند فشل بيانات الدخول أو حالة القفل أو عدم وجود دور "user"
     */
    public function join(int $projectId, array $data): array
    {
        return DB::transaction(function () use ($projectId, $data) {

            $existingUser = $this->users->findByEmail($data['email']);
            $isNewUser = false;

            if (! $existingUser) {
                $isNewUser = true;

                $user = $this->users->create([   
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'is_verified' => true,
                ]);
            } else {
                $user = $this->authenticateExistingUser($existingUser, $data['password']);
            }

            $this->ensureProjectMembership($user->id, $projectId);

            $sessionId = $this->sessions->create(
                userId: $user->id,
                ip: request()->ip(),
                userAgent: request()->userAgent()
            );

            $token = $this->jwt->generateToken($user, $sessionId);

            return [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'is_new_user' => $isNewUser,
                'project_id' => $projectId,
            ];
        });
    }

    /**
     * نفس منطق القفل والمحاولات الفاشلة الموجود في AuthService::attemptLogin
     * لكن بدون التحقق من is_verified (غير مرتبط بمنطق OTP الخاص بالمنصة)
     */
    private function authenticateExistingUser(User $user, string $password): User
    {
        if ($user->locked_until && now()->lessThan($user->locked_until)) {
            throw new Exception('Account locked until '.$user->locked_until);
        }

        if (! Hash::check($password, $user->password)) {
            $user->failed_attempts++;
            $update = ['failed_attempts' => $user->failed_attempts];

            if ($user->failed_attempts >= 3) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['failed_attempts'] = 0;
            }

            $this->users->update($user, $update);

            throw new Exception('Invalid credentials');
        }

        $this->users->update($user, ['failed_attempts' => 0, 'locked_until' => null]);

        return $user;
    }

    /**
     * التأكد أن للمستخدم دوراً ضمن هذا المشروع تحديداً
     * إذا لم يكن له أي دور بعد → نُسنِد الدور العام "user" ضمن هذا المشروع
     * إذا كان له دور بالفعل (حتى لو أعلى من user) → لا نغيّر شيئاً (Idempotent)
     */
    private function ensureProjectMembership(int $userId, int $projectId): void
    {
        $existingAssignment = $this->operationsRepo->findUserRoleAssignment($userId, $projectId);

        if ($existingAssignment) {
            return;
        }

        $memberRole = $this->operationsService->findGlobalRoleByNameService('user');

        if (! $memberRole) {
            throw new Exception('Default "user" role not found. Please run RolesAndPermissionsSeeder.');
        }

        $this->operationsService->assignRoleToUserForProjectService([
            'user_id' => $userId,
            'role_id' => $memberRole->id,
            'project_id' => $projectId,
        ]);
    }

    public function listMembers(int $projectId)
    {
        return $this->operationsService->getProjectMembersService($projectId);
    }

    /**
     * إزالة عضوية المستخدم ضمن مشروع محدد فقط
     * لا يمس دوره العام في النظام (project_id = null) ولا جلسته الحالية
     * فعل Self-Service — المستخدم يزيل عضويته الخاصة، وليس Super Admin يزيل عضوية غيره
     */
    public function leave(int $userId, int $projectId): void
    {
        $this->operationsService->removeRoleFromUserForProjectService([
            'user_id' => $userId,
            'project_id' => $projectId,
        ]);
    }


}
