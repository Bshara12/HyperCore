<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateServiceRequest;
use App\Models\ServiceClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceAuthController extends Controller
{
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

        $privateKey = file_get_contents(storage_path('keys/jwt_private.pem'));

        $payload = [

            'iss' => 'auth-service',

            'client' => $client->name,

            'type' => 'service',

            'iat' => time(),

            'exp' => time() + 3600
        ];

        $token = JWT::encode($payload, $privateKey, 'RS256');

        return response()->json([
            'access_token' => $token
        ]);
    }
}
