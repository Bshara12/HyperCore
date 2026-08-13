<?php

namespace App\Http\Controllers;

use App\Services\KeyRotationService;
use App\Services\OperationServices;
use App\Services\ServiceAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HyperCoreController extends Controller
{
    protected $operations;
    protected $serviceAuth;
    protected $keyRotation;

    public function __construct(
        OperationServices $operationServices,
        ServiceAuthService $serviceAuthService,
        KeyRotationService $keyRotationService
    ) {
        $this->operations = $operationServices;
        $this->serviceAuth = $serviceAuthService;
        $this->keyRotation = $keyRotationService;
    }

    private function authorizeHyperCore(Request $request)
    {
        $userId = $this->authUserId($request);

        if (! $userId || ! $this->operations->isHyperCore($userId)) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        return $userId;
    }

    public function deleteUser(Request $request, int $id)
    {
        $auth = $this->authorizeHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $this->operations->deleteUser($id, $auth);
            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function deleteService(Request $request, int $id)
    {
        $auth = $this->authorizeHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $this->serviceAuth->deleteService($id, $auth);
            return response()->json(['message' => 'Service deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rotateKeys(Request $request)
    {
        $auth = $this->authorizeHyperCore($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        try {
            $this->keyRotation->rotate($auth);
            return response()->json(['message' => 'Keys rotated successfully. All existing tokens are now invalid.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
