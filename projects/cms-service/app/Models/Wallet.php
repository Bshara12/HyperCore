<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
  protected $fillable = ['user_id', 'balance', 'wallet_number'];
  protected $casts    = ['balance' => 'float'];

  public function sentTransactions(): HasMany
  {
    return $this->hasMany(Transaction::class, 'from_wallet_id');
  }

  public function receivedTransactions(): HasMany
  {
    return $this->hasMany(Transaction::class, 'to_wallet_id');
  }

  public function hasSufficientBalance(float $amount): bool
  {
    return $this->balance >= $amount;
  }
}
