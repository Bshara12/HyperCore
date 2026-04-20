<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\DTOs\Wishlist\WishlistDetailsDTO;
use App\Domains\E_Commerce\DTOs\Wishlist\WishlistListDTO;
use App\Domains\E_Commerce\Requests\StoreWishlistRequest;
use App\Domains\E_Commerce\Requests\UpdateWishlistRequest;
use App\Domains\E_Commerce\Services\WishlistService;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService,
        protected AuthApiClient $authApiClient,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlists = $this->wishlistService->getUserWishlists(
            $user['id']
        );

        return response()->json([
            'message' => 'Wishlists fetched successfully.',
            'data' => $wishlists
            // 'data' => $user['id']
                // ->map(fn ($wishlist) => WishlistListDTO::fromModel($wishlist)->toArray())
                // ->toArray(),
        ]);
    }

    public function store(
        StoreWishlistRequest $request
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->createForUser(
            userId: $user['id'],
            data: $request->validated(),
        );

        return response()->json([
            'message' => 'Wishlist created successfully.',
            'data' => WishlistDetailsDTO::fromModel($wishlist)->toArray(),
        ], 201);
    }

    public function show(
        Request $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        return response()->json([
            'message' => 'Wishlist fetched successfully.',
            'data' => WishlistDetailsDTO::fromModel($wishlist)->toArray(),
        ]);
    }

    public function update(
        UpdateWishlistRequest $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            $wishlistId,
            $user['id']
        );

        $updatedWishlist = $this->wishlistService->update(
            $wishlist,
            $request->validated()
        );

        return response()->json([
            'message' => 'Wishlist updated successfully.',
            'data' => $updatedWishlist,
        ]);
    }

    public function destroy(
        Request $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $this->wishlistService->delete($wishlist);

        return response()->json([
            'message' => 'Wishlist deleted successfully.',
        ]);
    }

    public function generateShareLink(
        Request $request,
        int $wishlistId
    ): JsonResponse {
        $token = $request->bearerToken();

        $user = $this->authApiClient->getUserFromToken($token);

        $wishlist = $this->wishlistService->getUserWishlistOrFail(
            wishlistId: $wishlistId,
            userId: $user['id'],
        );

        $wishlist = $this->wishlistService->generateShareToken(
            $wishlist
        );

        return response()->json([
            'message' => 'Wishlist share link generated successfully.',
            'data' => [
                'share_token' => $wishlist->share_token,
            ],
        ]);
    }

    public function showSharedWishlist(
        string $shareToken
    ): JsonResponse {
        $wishlist = $this->wishlistService->getPublicWishlist(
            $shareToken
        );

        return response()->json([
            'message' => 'Shared wishlist fetched successfully.',
            'data' => WishlistDetailsDTO::fromModel($wishlist)->toArray(),
        ]);
    }
}
