<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;
use App\Domains\E_Commerce\Requests\CreateReturnRequestRequest;
use App\Domains\E_Commerce\Requests\UpdateReturnRequestRequest;
use App\Domains\E_Commerce\Services\ReturnRequestService;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
  public function __construct(
    protected ReturnRequestService $service
  ) {}

  public function store(CreateReturnRequestRequest $request)
  {
    $dto = CreateReturnRequestDTO::fromRequest($request);

    $data = $this->service->create($dto);

    return response()->json([
      'message' => 'Return request created',
      'data' => $data,
    ]);
  }

  public function update(UpdateReturnRequestRequest $request, int $id)
  {
    $dto = new UpdateReturnRequestDTO($id, $request->status);

    $data = $this->service->update($dto);

    return response()->json([
      'message' => 'Return request updated',
      'data' => $data,
    ]);
  }

  public function index(Request $request)
  {
    $user = $request->attributes->get('auth_user');
    // 🔥 Authorization
    if ($user['roles'][0]['name'] !== 'owner') {
      throw new \Exception('Unauthorized');
    }

    $dto = GetReturnRequestsDTO::fromRequest($request);

    $data = $this->service->getAll($dto);

    return response()->json([
      'message' => 'Return requests fetched successfully',
      'data' => $data,
    ]);
  }
}
