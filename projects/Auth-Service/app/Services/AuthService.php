<?php

namespace App\Services;

use App\Events\SystemLogEvent;
use App\Models\User;
use App\Repositories\SessionRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\OtpService;

class AuthService
{
    protected $users;
    protected $jwt;
    protected $otp;
    protected $operations;
    protected $sessions;

    public function __construct(
        UserRepositoryInterface $users,
        JwtService $jwtService,
        OtpService $otpService,
        OperationServices $operationServices,
        SessionRepositoryInterface $sessionRepository
    ) {
        $this->users = $users;
        $this->jwt = $jwtService;
        $this->otp = $otpService;
        $this->operations = $operationServices;
        $this->sessions = $sessionRepository;
    }

    public function registerService(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $user = $this->users->create($data);

        $this->operations->assignDefaultRegistrationRole($user->id);

        $this->otp->send($user);
        $this->log($user->id, 'register', []);

        event(new SystemLogEvent(
            module: 'auth',
            eventType: 'audit',
            userId: $user->id ?? null,
            entityType: 'user',
            entityId: null,
            oldValues: null,
            newValues: null
        ));

        return $user;
    }

    public function verifyOTP(User $user, string $otp): array
    {
        // ✅ فحص القفل أولاً — قبل أي مقارنة، لمنع استمرار محاولات التخمين خلال فترة القفل
        if ($user->locked_until && now()->lessThan($user->locked_until)) {
            return ['success' => false, 'message' => 'Account locked until '.$user->locked_until];
        }

        if (! $user->otp_code || $user->otp_code !== $otp) {
            $user->failed_attempts++;
            $update = ['failed_attempts' => $user->failed_attempts];

            // ✅ نفس عتبة الـ 3 محاولات المستخدمة بـ attemptLogin بالضبط
            if ($user->failed_attempts >= 3) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['failed_attempts'] = 0;
                $this->log($user->id, 'otp_verification_locked', []);
            }

            $this->users->update($user, $update);
            $this->log($user->id, 'otp_verification_failed', []);

            return ['success' => false, 'message' => 'Invalid OTP'];
        }

        if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return ['success' => false, 'message' => 'OTP expired'];
        }

        $this->users->update($user, [
            'is_verified' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        $this->log($user->id, 'otp_verified', []);

        return ['success' => true];
    }

    public function log($userId, string $action, ?array $meta)
    {
        Log::info('Auth Service Action', [
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta,
            'ip' => request()->ip(),
        ]);
    }

    public function attemptLogin(string $identifier, string $password)
    {
        $user = $this->users->findByEmail($identifier);
        $ip = request()->ip();

        if (! $user) {
            $this->log(null, 'login_failed', ['identifier' => $identifier, 'ip' => $ip]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($user->locked_until && now()->lessThan($user->locked_until)) {
            return ['success' => false, 'message' => 'Account locked until '.$user->locked_until];
        }

        if (! Hash::check($password, $user->password)) {
            $user->failed_attempts++;
            $update = ['failed_attempts' => $user->failed_attempts];

            if ($user->failed_attempts >= 3) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['failed_attempts'] = 0;
                $this->log($user->id, 'account_locked', []);
            }

            $this->users->update($user, $update);
            $this->log($user->id, 'login_failed', ['ip' => $ip]);

            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        $this->users->update($user, ['failed_attempts' => 0, 'locked_until' => null]);

        return ['success' => true, 'user' => $user];
    }

    public function logoutService(string $accessToken, $decoded)
    {
        $payload = $this->jwt->validateToken($accessToken);

        if (! $payload) {
            throw new Exception('Invalid token');
        }

        $sessionId = $payload->sid ?? null;

        if (! $sessionId) {
            throw new Exception('Session ID missing');
        }

        // 🔴 إصلاح الثغرة الحرجة: صار كل الإبطال مربوط فعلياً بـ session_id
        // (الجلسة + refresh tokens المرتبطة بها فقط، لا كل جلسات المستخدم) + الـ access token بالـ blacklist
        // كعملية واحدة ذرية (transaction) عبر Repository
        $this->sessions->revokeSessionCompletely(
            $sessionId,
            $payload->jti,
            Carbon::createFromTimestamp($payload->exp)
        );
    }

    /**
     * ✅ منطق كان بالكامل داخل AuthController::refresh() (DB::table مباشرة بالـ Controller)
     * انتقل هون ليتوافق مع Request→Controller→Service→Repository
     */
    public function refreshTokens(string $refreshToken): array
    {
        $decoded = $this->jwt->validateToken($refreshToken);

        if (! $decoded || $decoded->type !== 'refresh') {
            return ['success' => false, 'message' => 'Invalid refresh token'];
        }

        $record = $this->sessions->findValidRefreshToken($decoded->jti);

        if (! $record || now()->gt($record->expires_at)) {
            return ['success' => false, 'message' => 'Refresh token expired'];
        }

        $user = $this->users->findById($decoded->sub);

        if (! $user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Rotation: إبطال القديم قبل توليد الجديد لمنع إعادة الاستخدام
        $this->sessions->revokeRefreshToken($decoded->jti);

        return [
            'success' => true,
            'access_token' => $this->jwt->generateToken($user, $record->session_id),
            'refresh_token' => $this->jwt->generateRefreshToken($user, $record->session_id),
        ];
    }

    public function changePassword($data)
    {
        if (! Hash::check($data['current_password'], $data['user']->password)) {
            throw new Exception('Current password is incorrect');
        }

        $this->users->updatePassword(
            $data['user']->id,
            Hash::make($data['new_password'])
        );

        // ✅ إجراء أمان: إلغاء كل الجلسات الأخرى فوراً بعد تغيير كلمة المرور
        // (لو الحساب كان مخترق، أي token مسروق سابقاً ينقطع فوراً، ما عدا الجلسة الحالية)
        $this->sessions->revokeAllUserSessionsExcept(
            $data['user']->id,
            $data['current_session_id'] ?? null
        );
    }

    public function getUsersByIds(array $ids): Collection
    {
        $ids = array_unique($ids);

        return $this->users->getUsersByIds($ids);
    }
}
