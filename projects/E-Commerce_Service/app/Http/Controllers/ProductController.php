<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
  public function __construct(private ProductService $service) {}

  public function index(string $dataTypeSlug, Request $request)
  {
    return $this->service->getProducts(
      $dataTypeSlug,
      $request->code
    );
  }
}


// POST /pricing/calculate
// {
//   "entry_ids": [1,2,3],
//   "code": "SALE20"
// }