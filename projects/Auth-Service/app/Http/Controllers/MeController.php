<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MeController extends Controller
{
    protected $jwt;
    public function __construct(JwtService $jwtService)
    {
        $this->jwt = $jwtService;
    }

    public function index(Request $request)
    {
        $token = $request->bearerToken();

        $publicKey = file_get_contents(storage_path('keys/jwt_public.pem'));

        $decoded = JWT::decode($token,new Key($publicKey,'RS256'));

        $user = User::find($decoded->sub);

        return response()->json($user);
    }

    public function profile($id) { // For services
        $user = User::find($id);
        $user->load('roles.permessions');
        return response()->json([
            'data' => $user,
        ]);
    }

    public function myProfile(Request $request) { // For users
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($decode->sub);
        $user->load('roles.permessions');
        return response()->json([
            'data' => $user,
        ]);
    }

}
