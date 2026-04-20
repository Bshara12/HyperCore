<?php
namespace App\Domains\CMS\DTOs;

class CreateDataEntryDTO
{
    public function __construct(
        public int $projectId,
        public int $dataTypeId,
        public string $status,
        public int $createdBy,
        public array $values = [], // [field_id => [lang => value]]
    ) {}
}