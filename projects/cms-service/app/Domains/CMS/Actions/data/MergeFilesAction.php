<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\Services\FileUploadService;

class MergeFilesAction
{
  public function __construct(
    private FileUploadService $uploader
  ) {}

  public function execute(array $values, array $files, int $projectId, int $dataTypeId): array
  {
    foreach ($files as $fieldId => $langs) {
      foreach ($langs as $lang => $uploadedFiles) {
        foreach ((array) $uploadedFiles as $file) {

          $path = $this->uploader->upload(
            $file,
            $projectId,
            $dataTypeId,
            (int) $fieldId
          );

          $values[$fieldId][$lang][] = $path;
        }
      }
    }

    return $values;
  }
}
