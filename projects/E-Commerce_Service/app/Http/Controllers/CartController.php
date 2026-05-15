<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Domains\E_Commerce\Requests\CreateCartRequest;
use App\Domains\E_Commerce\Requests\RemoveCartItemsRequest;
use App\Domains\E_Commerce\Requests\UpdateCartRequest;
use App\Domains\E_Commerce\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
  public function __construct(
    protected CartService $cartService
  ) {}

  public function store(CreateCartRequest $request)
  {
    $dto = AddCartItemsDTO::fromRequest($request);
    $cart = $this->cartService->addItems($dto);

    return response()->json([
      'message' => 'Cart created successfully',
      'data' => $cart,
    ]);
  }

  public function show(Request $request)
  {
    $project_id = $request->project_id;
    $user_id = $request->attributes->get('auth_user')['id'];

    $cart = $this->cartService->getCart($project_id, $user_id);

    return response()->json([
      'message' => 'Cart fetched successfully',
      'data' => $cart,
    ]);
  }

  public function update(UpdateCartRequest $request)
  {
    $dto = UpdateCartItemsDTO::fromRequest($request);
    $cart = $this->cartService->updateItems($dto);

    return response()->json([
      'message' => 'Cart updated successfully',
      'data' => $cart,
    ]);
  }

  public function remove(RemoveCartItemsRequest $request)
  {
    $dto = RemoveCartItemsDTO::fromRequest($request);
    $cart = $this->cartService->removeItems($dto);

    return response()->json([
      'message' => 'Items removed successfully',
      'data' => $cart,
    ]);
  }

  public function clear(Request $request)
  {
    $cart = $this->cartService->clearCart($request->project_id, $request->attributes->get('auth_user')['id']);

    return response()->json([
      'message' => 'Cart cleared successfully',
      'data' => $cart,
    ]);
  }
}
