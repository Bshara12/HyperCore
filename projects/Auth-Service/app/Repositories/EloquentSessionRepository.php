<?php

namespace App\Repositories;

use App\Models\MySession;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\DB;

class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function createUserSession(int $userId, ?string $ip, ?string $userAgent, string $deviceName): string
    {
        $session = MySession::create([
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $deviceName,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return $session->id;
    }

    public function findActiveUserSession(string $sessionId)
    {
        return MySession::where('id', $sessionId)->first();
    }

    public function revokeUserSession(string $sessionId): void
    {
        MySession::where('id', $sessionId)->update(['revoked_at' => now()]);
    }

    public function createServiceSession(string $clientId, int $serviceClientId): string
    {
        $session = ServiceSession::create([
            'client_id' => $clientId,
            'service_client_id' => $serviceClientId,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return $session->id;
    }

    public function storeRefreshToken(int $userId, string $tokenId, string $sessionId, \DateTimeInterface $expiresAt): void
    {
        DB::table('refresh_tokens')->insert([
            'user_id' => $userId,
            'token_id' => $tokenId,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findValidRefreshToken(string $tokenId)
    {
        return DB::table('refresh_tokens')
            ->where('token_id', $tokenId)
            ->where('revoked', false)
            ->first();
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        DB::table('refresh_tokens')
            ->where('token_id', $tokenId)
            ->update(['revoked' => true, 'revoked_at' => now(), 'updated_at' => now()]);
    }

    public function revokeRefreshTokensForSession(string $sessionId): void
    {
        DB::table('refresh_tokens')
            ->where('session_id', $sessionId)
            ->update(['revoked' => true, 'revoked_at' => now(), 'updated_at' => now()]);
    }

    // ✅ جديد: نفس المنطق تماماً، بس جدول منفصل service_refresh_tokens
    public function storeServiceRefreshToken(int $serviceClientId, string $tokenId, string $sessionId, \DateTimeInterface $expiresAt): void
    {
        DB::table('service_refresh_tokens')->insert([
            'service_client_id' => $serviceClientId,
            'token_id' => $tokenId,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findValidServiceRefreshToken(string $tokenId)
    {
        return DB::table('service_refresh_tokens')
            ->where('token_id', $tokenId)
            ->where('revoked', false)
            ->first();
    }

    public function revokeServiceRefreshToken(string $tokenId): void
    {
        DB::table('service_refresh_tokens')
            ->where('token_id', $tokenId)
            ->update(['revoked' => true, 'revoked_at' => now(), 'updated_at' => now()]);
    }

    public function blacklistToken(string $tokenId, \DateTimeInterface $expiresAt): void
    {
        DB::table('token_blacklist')->insert([
            'token_id' => $tokenId,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function isTokenBlacklisted(string $tokenId): bool
    {
        return DB::table('token_blacklist')->where('token_id', $tokenId)->exists();
    }

    public function revokeSessionCompletely(string $sessionId, string $accessTokenId, \DateTimeInterface $accessTokenExpiresAt): void
    {
        DB::transaction(function () use ($sessionId, $accessTokenId, $accessTokenExpiresAt) {
            $this->revokeUserSession($sessionId);
            $this->revokeRefreshTokensForSession($sessionId);
            $this->blacklistToken($accessTokenId, $accessTokenExpiresAt);
        });
    }

    public function revokeAllUserSessionsExcept(int $userId, ?string $exceptSessionId = null): void
    {
        DB::transaction(function () use ($userId, $exceptSessionId) {
            $query = MySession::where('user_id', $userId)->whereNull('revoked_at');

            if ($exceptSessionId) {
                $query->where('id', '!=', $exceptSessionId);
            }

            // ✅ نلغي كل جلسة + الـ refresh tokens المرتبطة فيها
            // (لا داعي لـ blacklist الـ access tokens تحديداً هون: JwtMiddleware أصلاً
            // بيتحقق من revoked_at بالجلسة بكل طلب، فإلغاء الجلسة كافٍ لإبطال أي access token مرتبط فيها)
            $query->pluck('id')->each(function (string $sessionId) {
                $this->revokeUserSession($sessionId);
                $this->revokeRefreshTokensForSession($sessionId);
            });
        });
    }
}
