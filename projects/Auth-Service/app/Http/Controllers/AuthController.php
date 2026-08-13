<?php

namespace App\Http\Controllers;

use App\Events\UserLoggedIn;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\GetUsersByIdsRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\VerifyOTPRequest;
use App\Repositories\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\OtpService;
use App\Services\SessionService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;
    protected $jwtService;
    protected $sessions;
    protected $otpService;
    protected $users;

    public function __construct(
        AuthService $authService,
        JwtService $jwtService,
        SessionService $sessionService,
        OtpService $otpService,
        UserRepositoryInterface $userRepository
    ) {
        $this->authService = $authService;
        $this->jwtService = $jwtService;
        $this->sessions = $sessionService;
        $this->otpService = $otpService;
        $this->users = $userRepository;
    }

    public function register(RegisterRequest $registerRequest)
    {
        $data = $registerRequest->only(['name', 'email', 'password']);
        $data['ip'] = $registerRequest->ip();
        $data['agent'] = $registerRequest->userAgent();
        $user = $this->authService->registerService($data);

        return response()->json([
            'message' => 'Register, Done',
            'user_id' => $user->id,
        ], 201);
    }

    public function verifyOTP(VerifyOTPRequest $verifyOTPRequest)
    {
        $user = $this->users->findById($verifyOTPRequest->user_id);
        if (! $user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }

        $result = $this->authService->verifyOTP($user, $verifyOTPRequest->otp);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $sessionId = $this->sessions->create(
            userId: $user->id,
            ip: $verifyOTPRequest->ip(),
            userAgent: $verifyOTPRequest->userAgent()
        );

        $token = $this->jwtService->generateToken($user, $sessionId);

        return response()->json([
            'message' => 'Verified',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl') * 60,
            'user' => $user,
        ]);
    }

    public function resendOTP(ResendOtpRequest $request)
    {
        $user = $this->users->findById($request->user_id);

        if (! $user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Account Already Verified'], 400);
        }

        $this->otpService->resend($user);

        return response()->json(['message' => 'OTP Resent']);
    }

    public function login(LoginRequest $loginRequest)
    {
        $res = $this->authService->attemptLogin($loginRequest->identifier, $loginRequest->password);
        if (! $res['success']) {
            return response()->json(['message' => $res['message']], 401);
        }

        $user = $res['user'];
        if (! $user->is_verified) {
            return response()->json(['message' => 'Account Not Verified!'], 403);
        }

        $sessionId = $this->sessions->create(
            userId: $user->id,
            ip: $loginRequest->ip(),
            userAgent: $loginRequest->userAgent()
        );

        event(new UserLoggedIn($user->id));

        return response()->json([
            'access_token' => $this->jwtService->generateToken($user, $sessionId),
            'refresh_token' => $this->jwtService->generateRefreshToken($user, $sessionId),
            'token_type' => 'Bearer',
            'user' => $user,
        ], 200);
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $result = $this->authService->refreshTokens($request->refresh_token);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 401);
        }

        return response()->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request, JwtService $jwtService)
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = substr($header, 7);
        $decoded = $request->attributes->get('jwt_payload');
        $this->authService->logoutService($token, $decoded);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->only(['current_password', 'new_password']);
        $data['user'] = $this->users->findById($userId);
        $data['current_session_id'] = $this->authSessionId($request); // ✅ جديد

        $this->authService->changePassword($data);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function getByIds(GetUsersByIdsRequest $request)
    {
        $users = $this->authService->getUsersByIds($request->validated('ids'));

        return response()->json([
            'message' => 'Users fetched successfully.',
            'data' => $users,
        ]);
    }
}
