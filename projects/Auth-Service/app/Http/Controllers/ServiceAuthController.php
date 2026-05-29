<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateServiceRequest;
use App\Models\ServiceClient;
use App\Services\JwtService;
use App\Services\SessionService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ServiceAuthController extends Controller
{
    protected $session;
    protected $jwt;
    public function __construct(SessionService $sessionService, JwtService $jwtService)
    {
        $this->session = $sessionService;
        $this->jwt = $jwtService;
    }

    public function createService(CreateServiceRequest $createServiceRequest) {
        $data = $createServiceRequest->only(['name', 'client_secret']);
        $data['client_secret'] = Hash::make($data['client_secret']);
        $data['client_id'] = (string) Str::ulid();
        return ServiceClient::create($data);
    }

    public function token(Request $request)
    {

        $client = ServiceClient::where('client_id', $request->client_id)->first();

        if (!$client) {
            return response()->json(['error' => 'Invalid client'], 401);
        }

        if (!Hash::check($request->client_secret, $client->client_secret)) {
            return response()->json(['error' => 'Invalid secret'], 401);
        }

        //Create Session:
        $sessionId = $this->session->createServiceSession(
            $client->client_id,
            $client->id
        );

        $token = $this->jwt->generateServiceToken($client, $sessionId);

        return response()->json([
            'access_token' => $token
        ]);
    }

    public function validateToken($token)
    {
        $publicKey = file_get_contents(storage_path('keys/public.key'));
        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // // تحقق من blacklist
            // $blacklisted = DB::table('token_blacklist')
            //     ->where('token_id', $decoded->jti)
            //     ->exists();

            // if ($blacklisted) {
            //     return null;
            // }

            return $decoded;

        } catch (Exception $e) {
            return null;
        }
    }

    public function getService(Request $request) {
        $token = $request->bearerToken();
        $decode = $this->jwt->validateToken($token);
        if(!$decode) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $service = ServiceClient::find($decode->sub);
        $service->load('sessions');
        return response()->json([
            'data' => $service
        ]);
    }
}
