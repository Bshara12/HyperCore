<?php

return [

    'private_key' => storage_path('keys/private.key'),

    'public_key' => storage_path('keys/public.key'),

    'issuer' => env('APP_URL', 'http://localhost'),

    'algo' => 'RS256',

    // 'ttl' => 60, // مدة صلاحية التوكن بالدقائق
    'access_ttl'  => 15,      // دقيقة
    'refresh_ttl' => 10080,   // 7 أيام بالدقائق
];
