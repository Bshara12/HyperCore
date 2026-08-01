<?php

namespace App\Repositories;

interface SessionRepositoryInterface
{
    // ─── User Sessions (my_sessions) ───────────────────────────────
    public function createUserSession(int $userId, ?string $ip, ?string $userAgent, string $deviceName): string;

    public function findActiveUserSession(string $sessionId);

    public function revokeUserSession(string $sessionId): void;

    // ─── Service Sessions (service_sessions) ───────────────────────
    public function createServiceSession(string $clientId, int $serviceClientId): string;

    // ─── User Refresh Tokens ─────────────────────────────────────────
    public function storeRefreshToken(int $userId, string $tokenId, string $sessionId, \DateTimeInterface $expiresAt): void;

    public function findValidRefreshToken(string $tokenId);

    public function revokeRefreshToken(string $tokenId): void;

    public function revokeRefreshTokensForSession(string $sessionId): void;

    // ✅ جديد: Service Refresh Tokens (جدول منفصل، لا علاقة له بـ refresh_tokens الخاص بالمستخدمين)
    public function storeServiceRefreshToken(int $serviceClientId, string $tokenId, string $sessionId, \DateTimeInterface $expiresAt): void;

    public function findValidServiceRefreshToken(string $tokenId);

    public function revokeServiceRefreshToken(string $tokenId): void;

    // ─── Access Token Blacklist ──────────────────────────────────────
    public function blacklistToken(string $tokenId, \DateTimeInterface $expiresAt): void;

    public function isTokenBlacklisted(string $tokenId): bool;

    // ─── عملية مركّبة: إبطال جلسة بالكامل (logout) ─────────────────
    public function revokeSessionCompletely(string $sessionId, string $accessTokenId, \DateTimeInterface $accessTokenExpiresAt): void;
}
