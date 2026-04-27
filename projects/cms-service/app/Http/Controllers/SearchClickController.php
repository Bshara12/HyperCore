<?php

namespace App\Http\Controllers;

use App\Domains\Search\Actions\LogClickAction;
use App\Domains\Search\DTOs\LogClickDTO;
use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchClickController extends Controller
{
  public function __construct(
    private LogClickAction $logClickAction,
  ) {}

  public function __invoke(Request $request): JsonResponse
  {

    $request->validate([
      'entry_id'        => ['required', 'integer'],
      'data_type_id'    => ['required', 'integer'],
      'result_position' => ['required', 'integer', 'min:1'],
      'search_log_id'   => ['nullable', 'integer'],
    ]);

    $projectId = CurrentProject::id();
    $user = $request->attributes->get('auth_user');

    $this->logClickAction->execute(new LogClickDTO(
      projectId: $projectId,
      entryId: $request->integer('entry_id'),
      dataTypeId: $request->integer('data_type_id'),
      resultPosition: $request->integer('result_position'),
      userId: $user["id"],
      searchLogId: $request->integer('search_log_id'),
      sessionId: $user["sessions"][0]["id"] ?? null,
    ));

    return response()->json(['message' => 'Click logged.']);
  }
}
