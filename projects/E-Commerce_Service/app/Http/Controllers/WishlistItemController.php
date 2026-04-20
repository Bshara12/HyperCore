<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\DTOs\Wishlist\WishlistDetailsDTO;
use App\Domains\E_Commerce\Requests\AddWishlistItemRequest;
use App\Domains\E_Commerce\Requests\ReorderWishlistItemsRequest;
use App\Domains\E_Commerce\Services\WishlistItemService;
use App\Domains\E_Commerce\Services\WishlistService;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistItemController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService,
        protected WishlistItemService $wishlistItemService,
        protected AuthApiClient $authApiClient,
    ) {
    }

    public function store(
        AddWishlistItemRequest $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $item = $this->wishlistItemService->addItem(
            wishlist: $wishlist,
            data: $request->validated(),
        );

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        return response()->json([
            'message' => 'Item added to wishlist successfully.',
            'data' => [
                'wishlist' => WishlistDetailsDTO::fromModel($wishlist)->toArray(),
                'item' => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'sort_order' => $item->sort_order,
                    'product_snapshot' => $item->product_snapshot,
                ],
            ],
        ], 201);
    }

    public function destroy(
        Request $request,
        int $wishlistId,
        int $itemId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $this->wishlistItemService->removeItem(
            wishlist: $wishlist,
            itemId: $itemId,
        );

        return response()->json([
            'message' => 'Wishlist item removed successfully.',
        ]);
    }

    public function reorder(
        ReorderWishlistItemsRequest $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $this->wishlistItemService->reorderItems(
            wishlist: $wishlist,
            items: $request->validated()['items'],
        );

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        return response()->json([
            'message' => 'Wishlist items reordered successfully.',
            'data' => WishlistDetailsDTO::fromModel($wishlist)->toArray(),
        ]);
    }

    public function moveToCart(
        Request $request,
        int $wishlistId,
        int $itemId
    ): JsonResponse {
        $projectId = (int) $request->header('X-Project-Id');
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $this->wishlistItemService->moveToCart(
            wishlist: $wishlist,
            itemId: $itemId,
            projectId: $projectId,
            userId: $user['id']
        );

        return response()->json([
            'message' => 'Item moved to cart successfully.'
        ]);
    }
}
