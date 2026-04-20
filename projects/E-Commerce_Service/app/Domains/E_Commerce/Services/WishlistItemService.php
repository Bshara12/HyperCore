<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistItemRepositoryInterface;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\CMS\CMSApiClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WishlistItemService
{
    public function __construct(
        protected WishlistItemRepositoryInterface $wishlistItemRepository,
        protected CMSApiClient $cmsApiClient,
        protected CartService $cartService,
    ) {
    }

    // protected function getProductFromCMS(int $productId): ?array
    // {
    //     $entries = $this->cmsApiClient->getEntriesByDataType('product');

    //     echo '<pre>';
    //     print_r($entries);
    //     exit;
    // }
    protected function getProductFromCMS(int $productId): ?array
    {
        $entries = $this->cmsApiClient->getEntriesByDataType('product');

        foreach ($entries as $entry) {
            if ((int) ($entry['id'] ?? 0) === $productId) {
                return $entry;
            }
        }

        return null;
    }

    public function getWishlistItems(Wishlist $wishlist): Collection
    {
        return $this->wishlistItemRepository
            ->getByWishlistId($wishlist->id);
    }

    public function addItem(Wishlist $wishlist, array $data): WishlistItem
    {
        $productId = $data['product_id'];
        $variantId = $data['variant_id'] ?? null;

        $alreadyExists = $this->wishlistItemRepository->existsInWishlist(
            $wishlist->id,
            $productId,
            $variantId
        );

        abort_if($alreadyExists, 422, 'Product already exists in wishlist.');

        $product = $this->getProductFromCMS($productId);

        abort_if(! $product, 404, 'Product not found.');

        $highestSortOrder = $this->wishlistItemRepository->getHighestSortOrder($wishlist->id);

        return $this->wishlistItemRepository->create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'sort_order' => $highestSortOrder + 1,
            'added_from_cart' => $data['added_from_cart'] ?? false,
            'product_snapshot' => [
                'name' => data_get($product, 'values.title'),
                'slug' => data_get($product, 'values.slug'),
                'thumbnail' => data_get($product, 'values.thumbnail'),
                'price' => data_get($product, 'values.price'),
                'currency' => data_get($product, 'values.currency'),
                'stock_status' => data_get($product, 'values.stock_status'),
                'sku' => data_get($product, 'values.sku'),
            ],
            'price_when_added' => data_get($product, 'values.price'),
            'notify_on_price_drop' => $data['notify_on_price_drop'] ?? false,
            'notify_on_back_in_stock' => $data['notify_on_back_in_stock'] ?? false,
        ]);
    }

    public function removeItem(Wishlist $wishlist, int $itemId): bool
    {
        $item = $this->wishlistItemRepository
            ->findByIdInWishlist($itemId, $wishlist->id);

        abort_if(! $item, 404, 'Wishlist item not found.');

        return $this->wishlistItemRepository->delete($item);
    }

    public function moveToCart(
        Wishlist $wishlist,
        int $itemId,
        int $projectId,
        int $userId
    ): void {
        DB::transaction(function () use (
            $wishlist,
            $itemId,
            $projectId,
            $userId
        ) {
            $item = $this->wishlistItemRepository
                ->findByIdInWishlist($itemId, $wishlist->id);

            abort_if(! $item, 404, 'Wishlist item not found.');

            $dto = new AddCartItemsDTO(
                project_id: $projectId,
                user_id: $userId,
                items: [
                    [
                        'item_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'quantity' => 1,
                    ]
                ]
            );

            $this->cartService->addItems($dto);

            $this->wishlistItemRepository->delete($item);
        });
    }

    public function reorderItems(Wishlist $wishlist, array $items): void
    {
        foreach ($items as $itemData) {
            $item = $this->wishlistItemRepository
                ->findByIdInWishlist(
                    $itemData['item_id'],
                    $wishlist->id
                );

            if (! $item) {
                continue;
            }

            $this->wishlistItemRepository->update($item, [
                'sort_order' => $itemData['sort_order'],
            ]);
        }
    }

    public function exists(
        Wishlist $wishlist,
        int $productId,
        ?int $variantId = null
    ): bool {
        return $this->wishlistItemRepository->existsInWishlist(
            $wishlist->id,
            $productId,
            $variantId
        );
    }
}
