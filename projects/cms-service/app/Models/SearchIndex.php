<?php

namespace App\Models;

use App\Domains\Search\Support\SearchTextBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
  use HasFactory;
    protected $table = 'search_indices';

    protected $fillable = [
        'entry_id',
        'data_type_id',
        'project_id',
        'language',
        'title',
        'content',
        'meta',
        'search_text',
        'data_type_slug',
        'status',
        'published_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * شبكة أمان: أي حفظ عبر Eloquent (factories, upsert, tinker) يُعيد
     * بناء search_text من title/content/meta تلقائياً.
     *
     * المسارات التي تكتب بـ DB::table()->insert() (reindex/seeder)
     * تبنيه صريحاً — لأن الـ query builder لا يُشغّل أحداث الموديل.
     */
    protected static function booted(): void
    {
        static::saving(function (self $index): void {
            // مُرِّر صريحاً من مسار الفهرسة → لا نُعيد بناءه
            if ($index->isDirty('search_text') && filled($index->search_text)) {
                return;
            }

            if (filled($index->search_text) && ! $index->isDirty(['title', 'content', 'meta'])) {
                return;
            }

            $index->search_text = (new SearchTextBuilder())->build(
                $index->title,
                $index->content,
                $index->getAttributes()['meta'] ?? null,
            ) ?: null;
        });
    }

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
