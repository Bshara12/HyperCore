<?php

namespace App\Domains\CMS\Services;

use Illuminate\Http\UploadedFile;
use App\Domains\CMS\Actions\Files\UploadFileToSupabaseAction;

class FileUploadService
{
  public function __construct(
    private UploadFileToSupabaseAction $uploadAction
  ) {}

  public function upload(
    UploadedFile $file,
    int $projectId,
    int $dataTypeId,
    int $fieldId
  ): string {

    $path = sprintf(
      'projects/%d/data-types/%d/fields/%d',
      $projectId,
      $dataTypeId,
      $fieldId
    );
    // dd($file, $path);
    return $this->uploadAction->execute($file, $path);
  }
}
