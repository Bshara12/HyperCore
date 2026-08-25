<?php

namespace Database\Seeders\Demo;

/**
 * Fixed identifiers shared by the demo seeders of every service.
 *
 * The services own separate databases, so nothing can look these up across a
 * boundary: CMS stores projects.owner_id as an Auth user id, E-Commerce stores
 * order_items.product_id as a CMS entry id, and Booking stores
 * resources.data_entry_id the same way. Pinning the ids is what lets the four
 * seeders be run independently, in any order, and still line up.
 *
 * A copy of this file lives in every service. Keep the values identical.
 */
final class DemoIds
{
    // ─── Users ────────────────────────────────────────────────
    public const ADMIN_USER_ID = 9001;          // user1@example.com

    public const OWNER_USER_ID = 9002;          // owner1@example.com

    public const CUSTOMER_ONE_ID = 9003;        // customer1@example.com

    public const CUSTOMER_TWO_ID = 9004;        // customer2@example.com

    public const CUSTOMER_THREE_ID = 9005;      // customer3@example.com

    public const ADMIN_EMAIL = 'user1@example.com';

    public const OWNER_EMAIL = 'owner1@example.com';

    public const DEMO_PASSWORD = 'password123';

    // ─── Projects owned by user1@example.com (admin) ──────────
    public const ADMIN_PROJECT_BLOG = 9101;

    public const ADMIN_PROJECT_STORE = 9102;

    public const ADMIN_PROJECT_CLINIC = 9103;

    // ─── Projects owned by owner1@example.com ─────────────────
    /** The comprehensive one: cms + ecommerce + booking, fully populated. */
    public const OWNER_PROJECT_MARKETPLACE = 9201;

    public const OWNER_PROJECT_JOURNAL = 9202;

    public const OWNER_PROJECT_STUDIO = 9203;

    // ─── Data types of the comprehensive project ──────────────
    public const MARKETPLACE_TYPE_CATEGORY = 9301;

    public const MARKETPLACE_TYPE_PRODUCT = 9302;

    public const MARKETPLACE_TYPE_ARTICLE = 9303;

    public const MARKETPLACE_TYPE_SERVICE = 9304;

    // ─── Entry id ranges of the comprehensive project ─────────
    // Categories 9401-9403, products 9411-9418, articles 9421-9424,
    // services 9431-9433.
    public const MARKETPLACE_CATEGORY_FIRST = 9401;

    public const MARKETPLACE_PRODUCT_FIRST = 9411;

    public const MARKETPLACE_ARTICLE_FIRST = 9421;

    public const MARKETPLACE_SERVICE_FIRST = 9431;

    /** @return int[] */
    public static function marketplaceProductIds(): array
    {
        return range(self::MARKETPLACE_PRODUCT_FIRST, self::MARKETPLACE_PRODUCT_FIRST + 7);
    }

    /** @return int[] */
    public static function marketplaceServiceIds(): array
    {
        return range(self::MARKETPLACE_SERVICE_FIRST, self::MARKETPLACE_SERVICE_FIRST + 2);
    }

    /** @return int[] */
    public static function customerIds(): array
    {
        return [self::CUSTOMER_ONE_ID, self::CUSTOMER_TWO_ID, self::CUSTOMER_THREE_ID];
    }

    // ─── Collections of the comprehensive project ─────────────
    public const MARKETPLACE_COLLECTION_FEATURED = 9501;

    public const MARKETPLACE_COLLECTION_AFFORDABLE = 9502;
}
