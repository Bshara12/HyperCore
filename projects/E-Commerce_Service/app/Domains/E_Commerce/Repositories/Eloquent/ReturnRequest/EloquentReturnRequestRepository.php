<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\ReturnRequest;

use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Models\return_requests;

class EloquentReturnRequestRepository implements ReturnRequestRepositoryInterface
{
    public function create(array $data)
    {
        return return_requests::create($data);
    }

    public function findById(int $id)
    {
        return return_requests::find($id);
    }

    public function findPendingByItem(int $orderItemId)
    {
        return return_requests::where('order_item_id', $orderItemId)
            ->where('status', 'pending')
            ->first();
    }

    public function update($model, array $data)
    {
        $model->update($data);

        return $model;
    }

    public function getByProject(int $projectId)
    {
        return return_requests::query()
            ->where('project_id', $projectId)
            ->with(['orderItem']) // إذا بدك تفاصيل العنصر
            ->latest()
            ->paginate(10);
    }
}
