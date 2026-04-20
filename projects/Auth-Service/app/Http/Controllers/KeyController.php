<?php

namespace App\Http\Controllers;

use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class KeyController extends Controller
{
    protected $jwt;

    public function __construct(JwtService $jwtService)
    {
        $this->jwt = $jwtService;
    }

    public function jwks()
    {
        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => 'auth-key-1',
                    'n' => config('jwt.public_modulus'),
                    'e' => 'AQAB'
                ]
            ]
        ]);
    }

    public function serviceToken(Request $request)
    {
        $client = DB::table('service_clients')->where('client_id', $request->client_id)->first();

        if (!$client || !Hash::check($request->client_secret, $client->client_secret)) {
            return response()->json(['error' => 'Invalid client'], 401);
        }

        $payload = [
            'iss' => 'auth-service',
            'client' => $client->name,
            'type' => 'service',
            'iat' => time(),
            'exp' => time() + 3600
        ];

        $token = JWT::encode($payload, $this->jwt->returnInfo()['private'], 'RS256');

        return response()->json([
            'access_token' => $token
        ]);
    }

    public function index()
    {
        $publicKey = file_get_contents(storage_path('keys/jwt_public.pem'));

        return response()->json([
            'key' => $publicKey
        ]);
    }
}
