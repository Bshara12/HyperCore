<?php

namespace App\Domains\E_Commerce\Support;

class CacheKeys
{
    const TTL_SHORT = 300;    // 5 دقائق

    const TTL_MEDIUM = 3600;   // ساعة

    const TTL_LONG = 86400;  // يوم

    // ============================================
    // 🔑 Offers (E-Commerce)
    // ============================================
    public static function offers(int $projectId): string
    {
        return "project:{$projectId}:offers";
    }

    public static function offer(int $collectionId): string
    {
        return "offers:collection:{$collectionId}";
    }

    public static function offerBySlug(string $slug): string
    {
        return "offers:slug:{$slug}";
    }

    // ============================================
    // 🔑 Cart
    // ============================================
    public static function cart(int $userId, int $projectId): string
    {
        return "user:{$userId}:project:{$projectId}:cart";
    }

    // ============================================
    // 🔑 Orders
    // ============================================
    public static function userOrders(int $userId, int $projectId): string
    {
        return "user:{$userId}:project:{$projectId}:orders";
    }

    public static function order(int $orderId, int $userId): string
    {
        return "user:{$userId}:orders:{$orderId}";
    }

    public static function adminOrders(int $projectId, string $filtersHash): string
    {
        return "project:{$projectId}:admin:orders:{$filtersHash}";
    }
}
