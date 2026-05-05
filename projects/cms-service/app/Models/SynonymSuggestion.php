<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SynonymSuggestion extends Model
{
    protected $table = 'synonym_suggestions';

    protected $fillable = [
        'project_id', 'word_a', 'word_b', 'language',
        'jaccard_score', 'cooccurrence_count', 'confidence_score',
        'word_a_count', 'word_b_count', 'status',
        'reviewer_notes', 'reviewed_by', 'reviewed_at', 'last_computed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'last_computed_at' => 'datetime',
        'jaccard_score' => 'float',
        'confidence_score' => 'float',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeHighConfidence($query, float $threshold = 0.5)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }
}
