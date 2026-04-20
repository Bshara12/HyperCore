<?php

return [

  'default' => env('PAYMENT_GATEWAY', 'stripe'),

  'gateways' => [

    'stripe' => [
      'secret_key'      => env('STRIPE_SECRET_KEY'),
      'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    ],

    'paypal' => [
      'environment' => env('BRAINTREE_ENV', 'sandbox'),
      'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
      'public_key'  => env('BRAINTREE_PUBLIC_KEY'),
      'private_key' => env('BRAINTREE_PRIVATE_KEY'),
    ],

  ],

];
