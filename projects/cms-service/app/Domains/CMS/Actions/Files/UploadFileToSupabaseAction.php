<?php

namespace App\Domains\CMS\Actions\Files;

use App\Events\SystemLogEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFileToSupabaseAction
{
  public function execute(UploadedFile $file, string $path): string
  {
    $filePath = Storage::disk('supabase')->putFile($path, $file);
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'upload_file',
      userId: null,
      entityType: 'file',
      entityId: null
    ));
    return $filePath; // نخزن المسار فقط
  }
}
