<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
  use SoftDeletes;

  protected $fillable = [
    'user_id',
    'title',
    'provisioned_project_id',
    'status',
  ];

  protected $casts = [
    'provisioned_project_id' => 'integer',
  ];

  // ─── Relations ────────────────────────────────────────────
  public function messages(): HasMany
  {
    return $this->hasMany(AiMessage::class, 'conversation_id')
      ->orderBy('sequence');
  }

  public function lastMessage(): HasMany
  {
    return $this->hasMany(AiMessage::class, 'conversation_id')
      ->latest('sequence')
      ->limit(1);
  }
}
