<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionItem extends Model
{
  use HasFactory;
    protected $fillable = [
        'collection_id',
        'item_id',
        'sort_order',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(DataCollection::class, 'collection_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DataEntry::class, 'item_id');
    }
}
