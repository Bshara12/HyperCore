<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    protected $table = 'search_indices';

    protected $fillable = [
        'entry_id',
        'data_type_id',
        'project_id',
        'language',
        'title',
        'content',
        'meta',
        'status',
        'published_at',
    ];

    protected $casts = [
        'meta'         => 'array',
        'published_at' => 'datetime',
    ];

    // ─── Scope: فلترة حسب المشروع واللغة ───────────────────────────────
    public function scopeForProject($query, int $projectId): mixed
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForLanguage($query, string $language): mixed
    {
        return $query->where('language', $language);
    }

    public function scopePublished($query): mixed
    {
        return $query->where('status', 'published');
    }
}