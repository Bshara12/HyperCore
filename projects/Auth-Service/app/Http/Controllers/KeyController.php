<?php

namespace App\Http\Controllers;

class KeyController extends Controller
{
    public function jwks()
    {
        $publicKeyPem = file_get_contents(storage_path('keys/public.key'));
        $keyResource = openssl_pkey_get_public($publicKeyPem);

        if ($keyResource === false) {
            return response()->json(['message' => 'Public key could not be read'], 500);
        }

        $details = openssl_pkey_get_details($keyResource);

        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => 'auth-key-1',
                    'n' => $this->base64UrlEncode($details['rsa']['n']),
                    'e' => $this->base64UrlEncode($details['rsa']['e']),
                ],
            ],
        ]);
    }

    public function index()
    {
        $publicKey = file_get_contents(storage_path('keys/public.key'));

        return response()->json([
            'key' => $publicKey,
        ]);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
