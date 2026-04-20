<?php
namespace App\Domains\CMS\Services\Stock;

use App\Domains\CMS\Actions\Stock\DecrementStockAction;
use Illuminate\Support\Facades\DB;

class DecrementStockService
{
  public function __construct(
    protected DecrementStockAction $action
  ) {}

  public function execute(array $items)
  {
    DB::transaction(function () use ($items) {
      $this->action->execute($items);
    });
  }
}