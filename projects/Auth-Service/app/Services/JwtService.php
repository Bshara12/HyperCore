<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
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
        // $this->privateKey = file_get_contents(config('jwt.private_key'));
        // $this->publicKey  = file_get_contents(config('jwt.public_key'));
        // // $this->privateKey = config('jwt.private_key');
        // // $this->publicKey  = config('jwt.public_key');
        // $this->issuer = config('jwt.issuer');
        // // $this->ttl = config('jwt.ttl', 900);
        // // $this->privateKey = file_get_contents(config('jwt.private_key'));
        // // $this->publicKey  = file_get_contents(config('jwt.public_key'));
        // $this->algo       = config('jwt.algo');
        // $this->ttl        = config('jwt.ttl');
    }

    public function generateToken($user, $sessionId)
    {
        // $payload = [
        //     'iss' => config('app.url'),
        //     'iat' => time(),
        //     'exp' => time() + ($this->ttl * 60),
        //     'sub' => $user->id,
        //     'email' => $user->email,
        // ];

        // return JWT::encode($payload, $this->privateKey, $this->algo);

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

        // $token = JWT::encode($payload, $this->privateKey, 'RS256');
        $token = JWT::encode($payload, $this->privateKey, $this->algo);

        // DB::table('personal_access_tokens')->insert([
        //     'tokenable_id' => (string) Str::ulid(),
        //     'tokenable_type' => 'platform',
        //     'name' => 'auth_service_token',
        //     'token' => $token,
        //     'expires_at' => time() + (config('jwt.access_ttl') * 60),
        // ]);

        return $token;
    }

    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, 'RS256'));

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

        // $token = JWT::encode($payload, $this->privateKey, 'RS256');
        $token = JWT::encode($payload, $this->privateKey, $this->algo);
        //
        return $token;
    }

    public function generateProjectToken($userId, $projectId, $roleName) {
        $jti = Str::uuid()->toString();

        $payload = [
            'sub' => $userId,
            'proj' => $projectId,
            'role' => $roleName,
            'type' => 'project',
            'jti' => $jti,
            'iat' => time(),
            'exp' => time() + 3600
        ];

        // $projectToken = JWT::encode($payload, $this->privateKey, 'RS256');
        $projectToken = JWT::encode($payload, $this->privateKey, $this->algo);
        return $projectToken;
    }


}
