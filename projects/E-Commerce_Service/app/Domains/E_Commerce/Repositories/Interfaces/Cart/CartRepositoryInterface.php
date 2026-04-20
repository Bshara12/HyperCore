<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Cart;

use App\Domains\E_Commerce\DTOs\Cart\CreateCartDTO;
use App\Models\Cart;

interface CartRepositoryInterface
{
  public function getOrCreate(int $project_id, int $user_id);
  public function findByProjectAndUser(int $project_id, int $user_id);
  public function loadItems(Cart $cart);
  public function findById(int $id);

  public function delete(int $id): void;
}
