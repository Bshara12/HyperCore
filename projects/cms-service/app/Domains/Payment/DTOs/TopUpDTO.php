<?php

namespace App\Domains\Payment\DTOs;

use App\Models\Wallet;
use Illuminate\Http\Request;

class TopUpDTO
{
  public function __construct(
    public readonly Wallet $wallet,
    public readonly int $amount,
    public readonly ?string $note,
  ) {}

  public static function fromRequest(Request $request): self
  {
    $wallet = Wallet::where('wallet_number', $request->wallet_number)->first() ?? null;
    if ($wallet === null) {
      throw new \Exception('Wallet not found with number: ' . $request->wallet_number);
    }
    return new self(
      wallet: $wallet,
      amount: $request->amount,
      note: $request->note ?? null
    );
  }
}
