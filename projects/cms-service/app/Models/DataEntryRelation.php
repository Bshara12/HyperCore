<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataEntryRelation extends Model
{
  use HasFactory;
    protected $fillable = [
        'data_entry_id',
        'related_entry_id',
        'data_type_relation_id',
    ];

    public function entry()
    {
        return $this->belongsTo(DataEntry::class, 'data_entry_id');
    }

    public function relatedEntry()
    {
        return $this->belongsTo(DataEntry::class, 'related_entry_id');
    }

    public function dataTypeRelation(): BelongsTo
    {
        return $this->belongsTo(DataTypeRelation::class, 'data_type_relation_id');
    }
}
