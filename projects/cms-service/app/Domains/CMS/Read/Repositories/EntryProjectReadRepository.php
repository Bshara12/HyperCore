<?php 

namespace App\Domains\CMS\Read\Repositories;

use Illuminate\Support\Facades\DB;

class EntryProjectReadRepository implements EntryProjectReadRepositoryInterface
{
    public function queryByProject(int $projectId, array $filters)
    {
        $query = DB::table('data_entries')
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            });

        // 🔍 search
        if (!empty($filters['search'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('data_entry_values as v')
                    ->whereRaw('v.data_entry_id = data_entries.id')
                    ->where('v.value', 'LIKE', "%{$filters['search']}%");
            });
        }

        // 📅 date filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('published_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('published_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}