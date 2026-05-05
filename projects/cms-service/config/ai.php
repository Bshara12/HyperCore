<?php

return [

  'providers' => [

    'gemini' => [
      'api_key' => env('GEMINI_API_KEY'),
      'model'   => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'openrouter' => [
      'api_key'  => env('OPENROUTER_API_KEY'),
      'model'    => env('OPENROUTER_MODEL', 'openrouter/free'),
      'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    ],

  ],
];
