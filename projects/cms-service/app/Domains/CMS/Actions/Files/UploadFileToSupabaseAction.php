<?php

namespace App\Domains\CMS\Actions\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFileToSupabaseAction
{
  public function execute(UploadedFile $file, string $path): string
  {
    $filePath = Storage::disk('supabase')->putFile($path, $file);
    return $filePath; // نخزن المسار فقط
  }
}
