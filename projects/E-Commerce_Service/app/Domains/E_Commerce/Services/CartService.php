<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Actions\Cart\AddCartItemAction;
use App\Domains\E_Commerce\Actions\Cart\ClearCartAction;
use App\Domains\E_Commerce\Actions\Cart\GetCartAction;
use App\Domains\E_Commerce\Actions\Cart\RemoveCartItemsAction;
use App\Domains\E_Commerce\Actions\Cart\UpdateCartItemsAction;
use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Services\CMS\CMSApiClient;
use DomainException;

class CartService
{
  public function __construct(
    protected AddCartItemAction $addItemAction,
    protected GetCartAction $getCartAction,
    protected CMSApiClient $cms,
    protected UpdateCartItemsAction $updateCartItemsAction,
    protected RemoveCartItemsAction $removeCartItemsAction,
    protected ClearCartAction $clearCartAction,
  ) {}

  public function addItems(AddCartItemsDTO $dto)
  {
    return $this->addItemAction->execute($dto);
  }

  public function getCart(int $project_id, int $user_id)
  {
    $cart = $this->getCartAction->execute($project_id, $user_id);

    if (!$cart) {
      throw new DomainException("You don't have a cart yet");
    }

    $items = collect($cart['items']);

    if ($items->isEmpty()) {
      return $cart;
    }

    $ids = $items->pluck('item_id')->toArray();
    $entries = $this->cms->getEntriesByIds($ids);

    $entriesMap = [];
    foreach ($entries as $entry) {
      $entriesMap[$entry['id']] = $entry;
    }

    $cart['items'] = $items->map(function ($item) use ($entriesMap) {
      $item['entry'] = $entriesMap[$item['item_id']] ?? null;
      return $item;
    })->values()->all();

    return $cart;
  }

  public function updateItems(UpdateCartItemsDTO $dto)
  {
    return $this->updateCartItemsAction->execute($dto);
  }

  public function removeItems(RemoveCartItemsDTO $dto)
  {
    return $this->removeCartItemsAction->execute($dto);
  }

  public function clearCart(int $project_id, int $user_id)
  {
    return $this->clearCartAction->execute($project_id, $user_id);
  }
}
