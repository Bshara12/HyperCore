<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

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
