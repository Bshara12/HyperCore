<?php

namespace App\Domains\Booking\Support;

class CacheKeys
{
    const TTL_SHORT = 300;    // 5 دقائق

    const TTL_MEDIUM = 3600;   // ساعة

    const TTL_LONG = 86400;  // يوم

    // ============================================
    // 🔑 Resources
    // ============================================
    public static function resourcesForUser(int $projectId, int $userId): string
    {
        return "project:{$projectId}user:{$userId}:resources";
    }

    public static function resources(int $projectId): string
    {
        return "project:{$projectId}:resources";
    }

    public static function resource(int $resourceId): string
    {
        return "resources:{$resourceId}";
    }

    // ============================================
    // 🔑 Bookings
    // ============================================
    public static function booking(int $bookingId): string
    {
        return "bookings:{$bookingId}";
    }

    public static function resourceBookings(int $resourceId, string $filtersHash): string
    {
        return "resources:{$resourceId}:bookings:{$filtersHash}";
    }

    public static function userBookings(int $userId): string
    {
        return "user:{$userId}:bookings";
    }
}
