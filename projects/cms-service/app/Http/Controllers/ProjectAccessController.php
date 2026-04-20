<?php

namespace App\Http\Controllers;

use App\Domains\Auth\DTOs\CheckProjectAccessDto;
use App\Domains\Auth\Requests\CheckProjectAccessRequest;
use App\Domains\Auth\Service\ProjectAccessService;
use Illuminate\Http\Request;

class ProjectAccessController extends Controller
{
  public function check(
    CheckProjectAccessRequest $request,
    ProjectAccessService $service
  ) {
    $dto = new CheckProjectAccessDto(
      $request->user_id,
      // $request->project_key
      $request->header('X-Project-Key')
    );

    $hasAccess = $service->check($dto);

    return response()->json([
      'has_access' => $hasAccess
    ]);
  }
}
