<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\GetUsersByIdsRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyOTPRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\SessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected $authService;
    protected $jwtService;
    protected $sessions;
    public function __construct(AuthService $authService, JwtService $jwtService, SessionService $sessionService)
    {
        $this->authService = $authService;
        $this->jwtService = $jwtService;
        $this->sessions = $sessionService;
    }

    public function register(RegisterRequest $registerRequest) {
        $data = $registerRequest->only(['name', 'email', 'password']);
        $data['ip'] = $registerRequest->ip();
        $data['agent'] = $registerRequest->userAgent();
        $user = $this->authService->registerService($data);

        return response()->json([
            'message' => 'Register, Done',
            'user_id' => $user->id
        ], 201);
    }

    public function verifyOTP(VerifyOTPRequest $verifyOTPRequest) {
        $user = User::find($verifyOTPRequest->user_id);
        if(!$user) {
            return response()->json([
                'message' => 'User Not Found'
            ], 404);
        }

        if(!$this->authService->verifyOTP($user, $verifyOTPRequest->otp)) {
            return response()->json([
                'message' => 'Invalid OTP'
            ],422);
        }

        //Create Session:
        $sessionId = $this->sessions->create(
                userId: $user->id,
                ip: $verifyOTPRequest->ip(),
                userAgent: $verifyOTPRequest->userAgent()
            );
        //create jwt access token
        $token = $this->jwtService->generateToken($user, $sessionId);

        return response()->json([
            'message' => 'Verified',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user
        ]);
    }

    public function resendOTP(Request $request) {
        $user = User::find($request->user_id);

        if(!$user) {
            return response()->json([
                'message' => 'User Not Found'
            ], 404);
        }

        if($user->is_verified) {
            return response()->json([
                'message' => 'Account Already Verified'
            ], 400);
        }

        $this->authService->generateOTP($user);


        return response()->json([
            'message' => 'OTP Resent'
        ]);
    }

    public function login(LoginRequest $loginRequest) {
        $res = $this->authService->attemptLogin($loginRequest->identifier, $loginRequest->password);
        if(!$res['success']) return response()->json(['message' => $res['message']], 401);

        $user = $res['user'];
        if(!$user->is_verified) return response()->json(['message' => 'Account Not Verified!'], 403);

        //Create Session:
        $sessionId = $this->sessions->create(
                userId: $user->id,
                ip: $loginRequest->ip(),
                userAgent: $loginRequest->userAgent()
            );
        return response()->json([
            'access_token'  => $this->jwtService->generateToken($user, $sessionId),
            'refresh_token' => $this->jwtService->generateRefreshToken($user, $sessionId),
            'token_type'    => 'Bearer',
            'user' => $user
        ], 200);
    }

    public function refresh(Request $request, JwtService $jwtService)
    {
        $refreshToken = $request->refresh_token;

        $decoded = $jwtService->validateToken($refreshToken);

        if (!$decoded || $decoded->type !== 'refresh') {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $record = DB::table('refresh_tokens')
            ->where('token_id', $decoded->jti)
            ->where('revoked', false)
            ->first();

        if (!$record || now()->gt($record->expires_at)) {
            return response()->json(['message' => 'Refresh token expired'], 401);
        }

        $user = User::find($decoded->sub);

        return response()->json([
            'refresh_token' => $jwtService->generateRefreshToken($user, $record->session_id),
        ]);
    }

    public function logout(Request $request, JwtService $jwtService)
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = substr($header, 7);
        // نأخذ البيانات من middleware مباشرة
        $decoded = $request->attributes->get('jwt_payload');
        $this->authService->logoutService($token, $decoded);

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $token = $request->bearerToken();
        $decode = $this->jwtService->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $user = User::find($decode->sub);
        $data = $request->only(['current_password', 'new_password']);
        $data['user'] = $user;
        $this->authService->changePassword($data);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    function getByIds(GetUsersByIdsRequest $request)
    {
        $users = $this->authService->getUsersByIds(
            $request->validated('ids')
        );

        return response()->json([
            'message' => 'Users fetched successfully.',
            'data' => $users
        ]);
    }
}
