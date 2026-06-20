<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

uses(RefreshDatabase::class);

test('jwks returns correct key structure', function () {
  $response = $this->getJson('/api/.well-known/jwks.json');

  $response->assertStatus(200)
    ->assertJsonStructure(['keys' => [['kty', 'alg', 'use', 'kid', 'n', 'e']]]);
});

test('index returns correct public key content', function () {
  $expectedKey = File::get(storage_path('keys/public.key'));

  // تم تعديل المسار ليتطابق مع routes/api.php
  $response = $this->getJson('/api/.well-known/jwks');

  $response->assertStatus(200)
    ->assertJson(['key' => $expectedKey]);
});
