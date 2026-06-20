<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\File;

trait InteractsWithJwtKeys
{
  protected function setupJwtKeys(): void
  {
    $path = storage_path('keys');
    if (!File::exists($path)) {
      File::makeDirectory($path, 0755, true);
    }

    // إنشاء مفتاح RSA حقيقي
    $config = ["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];
    $res = openssl_pkey_new($config);
    openssl_pkey_export($res, $privateKey);
    $publicKey = openssl_pkey_get_details($res)['key'];

    File::put($path . '/private.key', $privateKey);
    File::put($path . '/public.key', $publicKey);
  }

  protected function cleanupJwtKeys(): void
  {
    File::delete([storage_path('keys/private.key'), storage_path('keys/public.key')]);
  }
}
