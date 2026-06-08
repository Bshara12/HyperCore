<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiMessage extends Model
{
  use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'schema',
        'is_provisioned',
        'sequence',
        'created_at',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_provisioned' => 'boolean',
        'created_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
