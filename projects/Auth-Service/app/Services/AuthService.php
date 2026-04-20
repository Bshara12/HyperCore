<?php

namespace App\Services;

use App\Jobs\SendOTPMailJob;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Collection;

class AuthService {
    protected $users;
    protected $jwt;

    public function __construct(UserRepositoryInterface $users, JwtService $jwtService)
    {
        $this->users = $users;
        $this->jwt = $jwtService;
    }

    public function registerService(array $data):User {
        $data['password'] = Hash::make($data['password']);
        $user = $this->users->create($data);
        $this->generateOTP($user);
        $this->log($user->id, 'register', []);
        return $user;
    }

    public function generateOTP(User $user) {
        $otp = (string) rand(100000, 999999);

        $this->users->update($user,[
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'is_verified' => false
        ]);

        SendOTPMailJob::dispatch($user, $otp);

        $this->log($user->id, 'otp_send', ['otp_length' => 6]);
    }

    public function verifyOTP(User $user, string $otp):bool  {
        if(!$user->otp_code || $user->otp_code !== $otp) return false;

        if($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) return false;

        $this->users->update($user, [
            'is_verified' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $this->log($user->id, 'otp_verified', []);
        return true;
    }

    public function log($userId, string $action, ?array $meta) {
        Log::info('Auth Service Action', [
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta,
            'ip' => request()->ip(),
        ]);
    }

    public function attemptLogin(string $identifier, string $password) {
        $user = $this->users->findByEmail($identifier);
        $ip = request()->ip();

        if (!$user) {
            $this->log(null, 'login_failed', ['identifier' => $identifier, 'ip' => $ip]);
        return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($user->locked_until && now()->lessThan($user->locked_until)) {
            return ['success' => false, 'message' => 'Account locked until ' . $user->locked_until];
        }

        if(!Hash::check($password, $user->password)) {
            $user->failed_attempts++;
            $update = ['failed_attempts' => $user->failed_attempts];

            if($user->failed_attempts >= 3) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['failed_attempts'] = 0;
                $this->log($user->id, 'account_locked', []);
            }

            $this->users->update($user, $update);
            $this->log($user->id, 'login_failed', ['ip' => $ip]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        //passed
        $this->users->update($user, ['failed_attempts' => 0, 'locked_until' => null]);
        return ['success' => true, 'user' => $user];
    }

    public function logoutService(string $accessToken, $decoded) {
        $payload = $this->jwt->validateToken($accessToken);

        if (!$payload) {
            throw new Exception('Invalid token');
        }

        $sessionId = $payload->sid ?? null;

        if (!$sessionId) {
            throw new Exception('Session ID missing');
        }

        $this->users->revoke($accessToken, $decoded);
    }

    public  function changePassword($data) {
        if (!Hash::check($data['current_password'], $data['user']->password)) {
            throw new Exception('Current password is incorrect');
        }

        $this->users->updatePassword(
            $data['user']->id,
            Hash::make($data['new_password'])
        );
    }

    public function getUsersByIds(array $ids): Collection
    {
        $ids = array_unique($ids);

        return $this->users->getUsersByIds($ids);
    }
}
