<?php

namespace App\Services;

use App\Repositories\SessionRepositoryInterface;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class JwtService
{
    protected $privateKey;
    protected $publicKey;
    private string $issuer;
    protected $algo;
    protected $sessions;

    public function __construct(SessionRepositoryInterface $sessionRepository)
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
        $this->sessions = $sessionRepository;
    }

    public function generateToken($user, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $payload = [
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + (config('jwt.access_ttl') * 60),
            'sub' => $user->id,
            'sid' => $sessionId,
            'jti' => $jti,
            'type'=> 'platform',
        ];

        return JWT::encode($payload, $this->privateKey, $this->algo);
    }

    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algo));

            // ✅ فحص القائمة السوداء صار مفعّل هون — مكان مركزي واحد
            // يستفيد منه كل الـ middlewares تلقائياً (JwtMiddleware, ServiceAuthMiddleware)
            if (isset($decoded->jti) && $this->sessions->isTokenBlacklisted($decoded->jti)) {
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function generateRefreshToken($user, $sessionId)
    {
        $jti = Str::uuid()->toString();
        $expires = now()->addMinutes(config('jwt.refresh_ttl'));

        $this->sessions->storeRefreshToken($user->id, $jti, $sessionId, $expires);

        $payload = [
            'exp' => $expires->timestamp,
            'sub' => $user->id,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => time(),
            'type'=> 'refresh',
        ];

        return JWT::encode($payload, $this->privateKey, $this->algo);
    }

    public function generateServiceToken($service, $sessionId)
    {
        $jti = Str::uuid()->toString();

        $payload = [
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + (config('jwt.access_ttl') * 60),
            'sub' => $service->id,
            'sid' => $sessionId,
            'jti' => $jti,
            'type'=> 'service',
        ];

        return JWT::encode($payload, $this->privateKey, $this->algo);
    }

    public function generateServiceRefreshToken($service, $sessionId)
    {
        $jti = Str::uuid()->toString();
        $expires = now()->addMinutes(config('jwt.refresh_ttl'));

        // ✅ صارت تخزّن بجدول service_refresh_tokens المخصص، مش refresh_tokens (خاص بالمستخدمين فقط)
        $this->sessions->storeServiceRefreshToken($service->id, $jti, $sessionId, $expires);

        $payload = [
            'exp' => $expires->timestamp,
            'sub' => $service->id,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => time(),
            'type'=> 'refresh',
        ];

        return JWT::encode($payload, $this->privateKey, $this->algo);
    }
}
