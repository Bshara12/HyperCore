<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateServiceRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\ServiceTokenRequest;
use App\Services\ServiceAuthService;
use Illuminate\Http\Request;

class ServiceAuthController extends Controller
{
    protected $serviceAuth;

    public function __construct(ServiceAuthService $serviceAuthService)
    {
        $this->serviceAuth = $serviceAuthService;
    }

    public function createService(CreateServiceRequest $request)
    {
        $service = $this->serviceAuth->createService($request->name, $request->client_secret);

        return response()->json($service);
    }

    public function token(ServiceTokenRequest $request)
    {
        $result = $this->serviceAuth->issueToken($request->client_id, $request->client_secret);

        if (! $result['success']) {
            return response()->json(['error' => $result['message']], 401);
        }

        return response()->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $result = $this->serviceAuth->refreshTokens($request->refresh_token);

        if (! $result['success']) {
            return response()->json(['error' => $result['message']], 401);
        }

        return response()->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function getService(Request $request)
    {
        $serviceId = $request->attributes->get('auth_service_id');
        $service = $this->serviceAuth->getServiceById($serviceId);

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        return response()->json(['data' => $service]);
    }
}
