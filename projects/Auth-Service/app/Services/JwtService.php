<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JwtService
{
    protected $privateKey;
    protected $publicKey;
    private string $issuer;
    protected $algo;
    protected $ttl;

    public function returnInfo()  {
        $data = [
                'private' => $this->privateKey,
                'public' => $this->publicKey,
            ];
        return $data;
    }
    public function __construct()
    {
        $privatePath = config('jwt.private_key');
        $publicPath  = config('jwt.public_key');

        if (!file_exists($privatePath)) {
            throw new \Exception("Private key file not found: {$privatePath}");
        }

        if (!file_exists($publicPath)) {
            throw new \Exception("Public key file not found: {$publicPath}");
        }

        $this->privateKey = file_get_contents($privatePath);
        $this->publicKey  = file_get_contents($publicPath);

        if (!$this->privateKey) {
            throw new \Exception("Private key could not be read");
        }

        if (!$this->publicKey) {
            throw new \Exception("Public key could not be read");
        }

        $this->issuer = config('jwt.issuer');
        $this->algo   = config('jwt.algo');
        $this->ttl    = config('jwt.ttl');
    }

    public function generateToken($user, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $payload = [
            // 'iss' => config('app.url'),
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + (config('jwt.access_ttl') * 60),
            'sub' => $user->id,
            'sid' => $sessionId,
            'jti' => $jti,
            // 'type'=> 'access'
            'type'=> 'platform'
        ];

        $token = JWT::encode($payload, $this->privateKey, $this->algo);


        return $token;
    }

    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algo));
            // // تحقق من blacklist
            // $blacklisted = DB::table('token_blacklist')
            //     ->where('token_id', $decoded->jti)
            //     ->exists();

            // if ($blacklisted) {
            //     return null;
            // }

            return $decoded;

        } catch (Exception $e) {
            return null;
        }
    }

    public function generateRefreshToken($user, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $expires = now()->addMinutes(config('jwt.refresh_ttl'));

        DB::table('refresh_tokens')->insert([
            'user_id'    => $user->id,
            'token_id'   => $jti,
            'session_id' => $sessionId,
            'expires_at' => $expires,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'exp' => $expires->timestamp,
            'sub' => $user->id,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => time(),
            'type'=> 'refresh'
        ];

        $token = JWT::encode($payload, $this->privateKey, $this->algo);

        return $token;
    }

    public function generateServiceToken($service, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $payload = [
            // 'iss' => config('app.url'),
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + (config('jwt.access_ttl') * 60),
            'sub' => $service->id,
            'sid' => $sessionId,
            'jti' => $jti,
            'type'=> 'service',
        ];

        $token = JWT::encode($payload, $this->privateKey, $this->algo);

        return $token;
    }

    public function generateServiceRefreshToken($service, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $expires = now()->addMinutes(config('jwt.refresh_ttl'));

        DB::table('refresh_tokens')->insert([
            'user_id'    => $service->id,
            'token_id'   => $jti,
            'session_id' => $sessionId,
            'expires_at' => $expires,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'exp' => $expires->timestamp,
            'sub' => $service->id,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => time(),
            'type'=> 'refresh'
        ];

        $token = JWT::encode($payload, $this->privateKey, $this->algo);

        return $token;
    }
}
