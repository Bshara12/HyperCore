<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Actions\Data\CreateDataEntryAction;
use App\Domains\CMS\DTOs\Data\CreateDataEntryDto;
use App\Domains\CMS\Read\Services\EntryReadService;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Domains\CMS\Services\DataEntryService;
use App\Domains\CMS\Services\FileUploadService;
use App\Domains\CMS\Services\Versioning\VersionRestoreService;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Support\CurrentProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DataEntryController extends Controller
{

  public function __construct(
    private VersionRestoreService $versionRestoreService,
    private DataEntryService $service,
  ) {}

  public function store(
    DataEntryRequest $request,
    DataType $dataType,
    FileUploadService $uploader
  ) {

    $authUser = $request->attributes->get('auth_user');
    $userId = null;
    if (is_array($authUser) && isset($authUser['id'])) {
      $userId = (int) $authUser['id'];
    } elseif (is_object($authUser) && isset($authUser->id)) {
      $userId = (int) $authUser->id;
    }

    $userId = $userId ?? auth()->id();

    $values = $request->input('values', []);
    // dd($values);
    $files = $request->filesInput();

    foreach ($files as $fieldId => $langs) {
      foreach ($langs as $lang => $uploadedFiles) {
        foreach ((array) $uploadedFiles as $file) {

          $path = $uploader->upload(
            $file,
            CurrentProject::id(),
            $dataType->id,
            (int) $fieldId
          );

          $values[$fieldId][$lang][] = $path;
        }
      }
    }

    // $scheduledAt = $request->input('scheduled_at')
    //   ? Carbon::parse($request->input('scheduled_at'))->format('Y-m-d H:i:s')
    //   : null;

    $scheduledAt = null;

    if ($request->input('status') === 'scheduled') {
      $scheduledAt = Carbon::parse(
        $request->input('scheduled_at')
      )->format('Y-m-d H:i:s');
    }

    $dto = new CreateDataEntryDto(
      values: $values,
      seo: $request->input('seo'),
      relations: $request->input('relations'),
      status: $request->input('status', 'draft'),
      scheduled_at: $scheduledAt
    );

    $entry = $this->service->create(
      projectId: $request->projectId(),
      dataTypeId: $dataType->id,
      slug: $request->input('slug'),
      dto: $dto,
      userId: $userId
    );

    return response()->json($entry, 201);
  }


  public function update(DataEntryRequest $request, DataType $dataType, $entryslug)
  {
    $entry = DataEntry::query()->where('slug',$entryslug)->first();
    $authUser = $request->attributes->get('auth_user');
    $userId = null;
    if (is_array($authUser) && isset($authUser['id'])) {
      $userId = (int) $authUser['id'];
    } elseif (is_object($authUser) && isset($authUser->id)) {
      $userId = (int) $authUser->id;
    }

    $userId = $userId ?? auth()->id();
    $dto = CreateDataEntryDto::fromRequest($request);
    $entry = $this->service->update(
      $request,
      dto: $dto,
      userId: $userId
    );
    

    return response()->json([
      'message' => 'Data updated successfully',
      'entry' => $entry
    ]);
  }

  public function destroy(string $entry)
  {
    $projectId =  CurrentProject::id();
    $entryModel = DataEntry::query()
      ->where('project_id', $projectId)
      ->where('slug', $entry)
      ->firstOrFail();

    $this->service->destroy($entryModel->id, $projectId);
    return response()->json(['message' => 'Data deleted successfully']);
  }

  public function destroyByType(DataType $dataType, string $entry)
  {
    $projectId =  CurrentProject::id();
    $entryModel = DataEntry::query()
      ->where('project_id', $projectId)
      ->where('slug', $entry)
      ->firstOrFail();

    $this->service->destroy($entryModel->id, $projectId);
    return response()->json(['message' => 'Data deleted successfully']);
  }

  public function restore(int $versionId)
  {
    $this->versionRestoreService
      ->restore($versionId, auth()->id());

    return response()->json([
      'message' => 'Version restored successfully'
    ]);
  }
}
