<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Requests\StockRequest;
use App\Domains\CMS\Services\Stock\DecrementStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DataEntry;

// class StockController extends Controller
// {
//   public function decrement(Request $request)
//   {
//     $items = $request->input('items');

//     DB::transaction(function () use ($items) {

//       foreach ($items as $item) {

//         $entry = DataEntry::where('id', $item['product_id'])
//           ->lockForUpdate() // 🔥🔥🔥 أهم سطر
//           ->firstOrFail();

//         // 🟢 جيب count من values
//         $countValue = $entry->values()
//           ->whereHas('field', fn($q) => $q->where('name', 'count'))
//           ->first();

//         $currentStock = (int) $countValue->value;

//         if ($currentStock < $item['quantity']) {
//           throw new \Exception("Not enough stock for product {$entry->id}");
//         }

//         // 🟢 خصم الكمية
//         $newStock = $currentStock - $item['quantity'];

//         $countValue->update([
//           'value' => $newStock
//         ]);
//       }
//     });

//     return response()->json([
//       'message' => 'Stock updated successfully'
//     ]);
//   }
// }

class StockController extends Controller
{
  public function decrement(StockRequest $request, DecrementStockService $service)
  {
    $service->execute($request->items());

    return response()->json([
      'message' => 'Stock updated successfully'
    ]);
  }
}