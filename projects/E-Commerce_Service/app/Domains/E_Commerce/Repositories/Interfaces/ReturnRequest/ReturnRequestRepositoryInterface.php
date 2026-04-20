<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest;

interface ReturnRequestRepositoryInterface
{
  public function create(array $data);
  public function findById(int $id);
  public function findPendingByItem(int $orderItemId);
  public function update($model, array $data);
  public function getByProject(int $projectId);
}
