<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinProjectRequest;
use App\Http\Requests\LeaveProjectRequest;
use App\Services\ProjectMembershipService;
use Illuminate\Http\JsonResponse;

class ProjectMembershipController extends Controller
{
    public function __construct(
        private readonly ProjectMembershipService $service
    ) {}

    /**
     * يُستدعى فقط من CMS Service عبر X-Internal-Api-Key
     * وليس مباشرة من الـ Frontend
     */
    public function join(JoinProjectRequest $request, int $projectId): JsonResponse
    {
        try {
            $result = $this->service->join(
                $projectId,
                $request->only(['name', 'email', 'password'])
            );

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function members(int $projectId): JsonResponse
    {
        $members = $this->service->listMembers($projectId);

        return response()->json(['data' => $members]);
    }

    public function leave(LeaveProjectRequest $request, int $projectId): JsonResponse
    {
        $this->service->leave($request->integer('user_id'), $projectId);

        return response()->json(['message' => 'Left project successfully']);
    }
}
