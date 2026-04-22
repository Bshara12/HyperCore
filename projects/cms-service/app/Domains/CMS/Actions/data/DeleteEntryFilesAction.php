<?php

namespace App\Domains\CMS\Actions\Data;

use App\Domains\Core\Actions\Action;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Storage;

// class DeleteEntryFilesAction extends Action
// {
//   protected function circuitServiceName(): string
//   {
//     return 'dataEntry.deleteFiles';
//   }

//   public function __construct(
//     private DataEntryValueRepository $values
//   ) {}

//   public function execute(int $entryId): void
//   {
//     $this->run(function () use ($entryId) {

//       $values = $this->values->getForEntry($entryId);
//       foreach ($values as $value) {

//         $path = $value['value'];

//         if (!$path) {
//           continue;
//         }

//         // نتأكد أنه مسار ملف
//         if (!str_contains($path, 'projects/')) {
//           continue;
//         }

//         Storage::disk('supabase')->delete($path);
//       }
//     });
//   }
// }

class DeleteEntryFilesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataEntry.collectFiles';
  }

  public function __construct(
    private DataEntryValueRepository $values
  ) {}

  public function execute(int $entryId): array
  {
    return $this->run(function () use ($entryId) {

      $values = $this->values->getForEntry($entryId);

      $paths = [];

      foreach ($values as $value) {

        $path = $value['value'];

        if (!$path) {
          continue;
        }

        if (!str_contains($path, 'projects/')) {
          continue;
        }

        $paths[] = $path;
      }
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'storage_file',
        userId: null,
        entityType: 'data',
        entityId: $entryId
      ));
      return $paths;
    });
  }
}
