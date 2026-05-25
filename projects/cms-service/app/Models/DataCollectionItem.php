<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataCollectionItem extends Model
{
  use HasFactory;
    protected $fillable = [
        'collection_id',
        'item_id',
        'sort_order',
    ];

    public function collection()
    {
        return $this->belongsTo(DataCollection::class, 'collection_id');
    }

    public function entry()
    {
        return $this->belongsTo(DataEntry::class, 'item_id');
    }
}
